<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClamAvFileScanner implements FileScanner
{
    public function __construct(private readonly string $binary = 'clamdscan') {}

    public function scan(UploadedFile $file): void
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.attachments.upload_failed'),
            ]);
        }

        $process = new Process([$this->binary, '--no-summary', '--stdout', $path]);
        $process->setTimeout(30);
        $process->run();

        if ($process->getExitCode() === 1) {
            throw ValidationException::withMessages([
                'file' => __('ui.attachments.malware_detected'),
            ]);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Malware scan failed: '.$process->getErrorOutput());
        }
    }
}
