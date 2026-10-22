<?php

namespace Tests\Feature;

use App\Models\MemoryAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use DatabaseMigrations;

    private ?string $temporaryDirectory = null;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->temporaryDirectory !== null) {
            File::deleteDirectory($this->temporaryDirectory);
        }

        parent::tearDown();
    }

    public function test_sqlite_backup_creates_a_consistent_snapshot_and_prunes_old_files(): void
    {
        $root = $this->temporaryDirectory = storage_path('framework/testing/operations-'.Str::uuid());
        $backupDirectory = $root.'/backups';
        $database = $root.'/source.sqlite';
        File::ensureDirectoryExists($root);

        $pdo = new PDO('sqlite:'.$database);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (name) VALUES ('Ayesha')");
        $pdo = null;

        $originalDatabase = config('database.connections.sqlite.database');
        $originalBackupDirectory = config('realitylog.backup.directory');

        config([
            'database.connections.sqlite.database' => $database,
            'realitylog.backup.directory' => $backupDirectory,
        ]);

        try {
            $this->travelTo('2026-10-22 02:00:00');

            $this->artisan('backup:database', ['--keep' => 2])
                ->assertExitCode(0);

            $firstBackup = $backupDirectory.'/realitylog-20261022-020000.sqlite';
            $this->assertFileExists($firstBackup);

            $backupPdo = new PDO('sqlite:'.$firstBackup);
            $this->assertSame('Ayesha', $backupPdo->query('SELECT name FROM users')->fetchColumn());
            $backupPdo = null;

            $firstBackupTime = time() - 10;
            touch($firstBackup, $firstBackupTime);

            File::put($backupDirectory.'/realitylog-20261019-020000.sqlite', 'old backup');
            File::put($backupDirectory.'/realitylog-20261020-020000.sqlite', 'old backup');
            touch($backupDirectory.'/realitylog-20261019-020000.sqlite', $firstBackupTime - 172800);
            touch($backupDirectory.'/realitylog-20261020-020000.sqlite', $firstBackupTime - 86400);

            $this->travelTo('2026-10-23 02:00:00');

            $this->artisan('backup:database', ['--keep' => 2])
                ->assertExitCode(0);

            $backups = collect(File::files($backupDirectory))
                ->filter(fn ($file): bool => str_starts_with($file->getFilename(), 'realitylog-'))
                ->map(fn ($file): string => $file->getFilename())
                ->values()
                ->all();

            $this->assertCount(2, $backups, implode(', ', $backups));
            $this->assertContains('realitylog-20261023-020000.sqlite', $backups, implode(', ', $backups));
            $this->assertContains('realitylog-20261022-020000.sqlite', $backups, implode(', ', $backups));
            $this->assertNotContains('realitylog-20261019-020000.sqlite', $backups, implode(', ', $backups));
            $this->assertNotContains('realitylog-20261020-020000.sqlite', $backups, implode(', ', $backups));
        } finally {
            config([
                'database.connections.sqlite.database' => $originalDatabase,
                'realitylog.backup.directory' => $originalBackupDirectory,
            ]);
        }
    }

    public function test_orphaned_attachment_records_and_files_are_previewed_then_removed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'memories/'.$user->id.'/orphaned.pdf';
        Storage::disk('local')->put($path, 'orphaned attachment');

        DB::statement('PRAGMA foreign_keys = OFF');

        $attachment = MemoryAttachment::create([
            'memory_id' => 999999,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'orphaned.pdf',
            'mime_type' => 'application/pdf',
            'size' => 18,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        DB::statement('PRAGMA foreign_keys = ON');

        $this->artisan('attachments:prune-orphans', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('memory_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($path);

        $this->artisan('attachments:prune-orphans')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('memory_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
    }
}
