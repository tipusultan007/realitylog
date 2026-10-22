<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class AiRuntime
{
    public function python(): ?string
    {
        $configured = (string) config('realitylog.ai.python', '');

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($configured !== '' ? $configured : 'python3')
            ?? (new ExecutableFinder)->find('python');
    }

    public function tesseract(): ?string
    {
        return $this->binary('tesseract_binary', [
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        ]);
    }

    public function ffmpeg(): ?string
    {
        return $this->binary('ffmpeg_binary', []);
    }

    public function pdftoppm(): ?string
    {
        return $this->binary('pdftoppm_binary', []);
    }

    /**
     * @param  array<int, string>  $fallbacks
     */
    private function binary(string $configKey, array $fallbacks): ?string
    {
        $configured = (string) config("realitylog.ai.{$configKey}", '');
        $finder = new ExecutableFinder;

        foreach (array_filter([$configured, ...$fallbacks, ...$this->windowsCandidates($configKey)]) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }

            $found = $finder->find($candidate);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * WinGet installs tools outside the PATH inherited by long-running PHP processes.
     *
     * @return array<int, string>
     */
    private function windowsCandidates(string $configKey): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $localAppData = getenv('LOCALAPPDATA');

        if (! is_string($localAppData) || $localAppData === '') {
            return [];
        }

        return match ($configKey) {
            'ffmpeg_binary' => [
                $localAppData.'\\Microsoft\\WinGet\\Links\\ffmpeg.exe',
            ],
            'pdftoppm_binary' => glob(
                $localAppData.'\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_*\\poppler-*\\Library\\bin\\pdftoppm.exe'
            ) ?: [],
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $arguments
     * @return array<string, mixed>
     */
    public function runPython(string $script, array $arguments = [], ?string $stdin = null): array
    {
        $python = $this->python();

        if ($python === null) {
            throw new RuntimeException('Python is not available. Install Python and the packages in scripts/ai/requirements.txt.');
        }

        $path = base_path('scripts/ai/'.$script);

        if (! is_file($path)) {
            throw new RuntimeException("AI script [{$script}] is missing.");
        }

        $process = new Process([$python, $path, ...$arguments]);
        $process->setEnv([
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
        ]);
        $process->setTimeout((int) config('realitylog.ai.timeout', 600));

        if ($stdin !== null) {
            $process->setInput($stdin);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: "AI script [{$script}] failed.");
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("AI script [{$script}] returned invalid JSON.");
        }

        return $decoded;
    }

    public function temporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/tmp/'.$prefix.'-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($path);

        return $path;
    }
}
