<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : Number of daily backups to retain}';

    protected $description = 'Create a local database backup and prune older backups';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $directory = (string) config('realitylog.backup.directory', storage_path('app/backups'));
        File::ensureDirectoryExists($directory);

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $driver = $config['driver'] ?? null;
        $timestamp = now()->format('Ymd-His');

        $destination = match ($driver) {
            'sqlite' => $this->backupSqlite($config, $directory, $timestamp),
            'mysql', 'mariadb' => $this->backupMysql($config, $directory, $timestamp),
            'pgsql' => $this->backupPostgres($config, $directory, $timestamp),
            default => null,
        };

        if ($destination === null) {
            $this->error("Database driver [{$driver}] is not supported by this command.");

            return self::FAILURE;
        }

        $this->info("Backup written to {$destination}");

        $pruned = $this->prune($directory, $keep);
        $this->line("Pruned {$pruned} old backup(s); keeping {$keep}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backupSqlite(array $config, string $directory, string $timestamp): ?string
    {
        $database = (string) ($config['database'] ?? '');

        if ($database === '' || $database === ':memory:' || ! is_file($database)) {
            $this->error('The SQLite database file could not be found.');

            return null;
        }

        $destination = $this->availableBackupPath($directory, "realitylog-{$timestamp}.sqlite");

        try {
            $pdo = new PDO('sqlite:'.$database, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 30,
            ]);
            $pdo->exec('PRAGMA busy_timeout = 30000');
            $pdo->exec('VACUUM INTO '.$pdo->quote($destination));
            $pdo = null;
        } catch (Throwable $exception) {
            report($exception);

            File::delete($destination);
            $this->error('The SQLite database could not be backed up: '.$exception->getMessage());

            return null;
        }

        return $destination;
    }

    private function availableBackupPath(string $directory, string $filename): string
    {
        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($path)) {
            return $path;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);

        for ($suffix = 2; ; $suffix++) {
            $candidate = $directory.DIRECTORY_SEPARATOR."{$base}-{$suffix}.{$extension}";

            if (! File::exists($candidate)) {
                return $candidate;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backupMysql(array $config, string $directory, string $timestamp): ?string
    {
        $binary = (new ExecutableFinder)->find(
            (string) config('realitylog.backup.mysql_binary', 'mysqldump')
        );

        if ($binary === null) {
            $this->error('mysqldump was not found. Install the MySQL client tools or set MYSQLDUMP_BINARY.');

            return null;
        }

        $destination = $directory.DIRECTORY_SEPARATOR."realitylog-{$timestamp}.sql";
        $database = (string) ($config['database'] ?? '');

        $process = new Process([
            $binary,
            '--single-transaction',
            '--quick',
            '--lock-tables=false',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? 'root'),
            '--result-file='.$destination,
            $database,
        ]);
        $process->setTimeout(300);
        $process->setEnv(['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('mysqldump failed: '.$process->getErrorOutput());

            return null;
        }

        return $destination;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backupPostgres(array $config, string $directory, string $timestamp): ?string
    {
        $binary = (new ExecutableFinder)->find(
            (string) config('realitylog.backup.postgres_binary', 'pg_dump')
        );

        if ($binary === null) {
            $this->error('pg_dump was not found. Install the PostgreSQL client tools or set PG_DUMP_BINARY.');

            return null;
        }

        $destination = $directory.DIRECTORY_SEPARATOR."realitylog-{$timestamp}.sql";
        $process = new Process([
            $binary,
            '--no-owner',
            '--no-privileges',
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '5432'),
            '--username='.($config['username'] ?? 'postgres'),
            '--file='.$destination,
            (string) ($config['database'] ?? ''),
        ]);
        $process->setTimeout(300);
        $process->setEnv(['PGPASSWORD' => (string) ($config['password'] ?? '')]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('pg_dump failed: '.$process->getErrorOutput());

            return null;
        }

        return $destination;
    }

    private function prune(string $directory, int $keep): int
    {
        $backups = collect(File::files($directory))
            ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'realitylog-'))
            ->sortByDesc(fn ($file): int => $file->getMTime())
            ->values();

        return $backups->slice($keep)->reduce(function (int $count, $file): int {
            File::delete($file->getPathname());

            return $count + 1;
        }, 0);
    }
}
