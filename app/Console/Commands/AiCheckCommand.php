<?php

namespace App\Console\Commands;

use App\Services\Ai\AiRuntime;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Throwable;

class AiCheckCommand extends Command
{
    protected $signature = 'ai:check';

    protected $description = 'Check local OCR, transcription, and embedding prerequisites';

    public function handle(AiRuntime $runtime): int
    {
        $enabled = (bool) config('realitylog.ai.enabled');
        $ok = true;

        $this->line('Local AI: '.($enabled ? 'enabled' : 'disabled'));

        $ok = $this->checkBinary('Tesseract OCR', $runtime->tesseract(), ['--version']) && $ok;
        $ok = $this->checkBinary('FFmpeg', $runtime->ffmpeg(), ['-version']) && $ok;
        $ok = $this->checkBinary('Poppler (pdftoppm)', $runtime->pdftoppm(), ['-v']) && $ok;

        $tessdata = (string) config('realitylog.ai.tessdata_dir');
        $languages = (string) config('realitylog.ai.ocr_languages', 'eng+ben');

        foreach (explode('+', $languages) as $language) {
            $file = $tessdata.DIRECTORY_SEPARATOR.$language.'.traineddata';

            if (is_file($file)) {
                $this->line("  Language data: {$language} (found)");
            } else {
                $this->warn("  Language data: {$language} (missing at {$file})");
                $ok = false;
            }
        }

        $python = $runtime->python();

        if ($python === null) {
            $this->warn('  Python: not found');
            $ok = false;
        } else {
            $this->line("  Python: {$python}");
            $output = $this->output($python, ['-c', 'import faster_whisper, fastembed; print("packages ok")']);

            if (str_contains($output, 'packages ok')) {
                $this->line('  Python packages: faster-whisper and fastembed (found)');
            } else {
                $this->warn('  Python packages: missing. Run: python -m pip install -r scripts/ai/requirements.txt');
                $ok = false;
            }
        }

        $this->line('  Whisper model: '.config('realitylog.ai.whisper_model').' (downloaded on first use)');
        $this->line('  Embedding model: '.config('realitylog.ai.embedding_model').' (downloaded on first use)');

        if (! $enabled) {
            $this->warn('Local AI is disabled. Set LOCAL_AI_ENABLED=true to use it.');

            return self::SUCCESS;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function checkBinary(string $label, ?string $binary, array $arguments): bool
    {
        if ($binary === null) {
            $this->warn("  {$label}: not found");
            $this->warn('  Install it or set the matching binary path in .env.');

            return false;
        }

        $output = $this->output($binary, $arguments);
        $firstLine = trim(strtok($output, "\n") ?: $output);

        $this->line("  {$label}: {$binary}");
        $this->line('    '.($firstLine !== '' ? $firstLine : 'available'));

        return true;
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function output(string $binary, array $arguments): string
    {
        try {
            $process = new Process([$binary, ...$arguments]);
            $process->setTimeout(30);
            $process->run();

            return trim($process->getOutput()."\n".$process->getErrorOutput());
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }
}
