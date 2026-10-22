<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class OcrService
{
    public function __construct(private readonly AiRuntime $runtime) {}

    /**
     * @return array{text: string, status: string, message: ?string}
     */
    public function extract(string $path, ?string $languages = null): array
    {
        if (! config('realitylog.ai.enabled')) {
            return $this->unavailable('Local OCR is disabled on this server.');
        }

        $binary = $this->runtime->tesseract();

        if ($binary === null) {
            return $this->unavailable('Tesseract is not installed. Install it to read images automatically.');
        }

        if (! is_file($path)) {
            return ['text' => '', 'status' => 'failed', 'message' => 'The image file could not be read.'];
        }

        try {
            $arguments = [
                $binary,
                $path,
                'stdout',
                '-l',
                $languages ?: (string) config('realitylog.ai.ocr_languages', 'eng+ben'),
                '--psm',
                '6',
            ];

            $tessdata = (string) config('realitylog.ai.tessdata_dir', '');

            if ($tessdata !== '' && is_dir($tessdata)) {
                $arguments[] = '--tessdata-dir';
                $arguments[] = $tessdata;
            }

            $process = new Process($arguments);
            $process->setTimeout((int) config('realitylog.ai.timeout', 600));
            $process->run();

            if (! $process->isSuccessful()) {
                return [
                    'text' => '',
                    'status' => 'needs_review',
                    'message' => 'OCR could not read this image. The original is preserved.',
                ];
            }

            $text = $this->clean($process->getOutput());

            if ($text === '') {
                return [
                    'text' => '',
                    'status' => 'needs_review',
                    'message' => 'No readable text was found in this image. Add the key details manually.',
                ];
            }

            return ['text' => $text, 'status' => 'extracted', 'message' => null];
        } catch (Throwable $e) {
            report($e);

            return [
                'text' => '',
                'status' => 'failed',
                'message' => 'OCR failed. The original image is still safe.',
            ];
        }
    }

    /**
     * Render scanned PDF pages and OCR each one.
     *
     * @return array{text: string, status: string, message: ?string}
     */
    public function extractPdf(string $path): array
    {
        if (! config('realitylog.ai.enabled')) {
            return $this->unavailable('Local OCR is disabled on this server.');
        }

        $binary = $this->runtime->pdftoppm();

        if ($binary === null) {
            return $this->unavailable('Scanned PDF OCR requires Poppler (pdftoppm).');
        }

        $directory = $this->runtime->temporaryDirectory('pdf-ocr');
        $prefix = $directory.DIRECTORY_SEPARATOR.'page';

        try {
            $process = new Process([
                $binary,
                '-png',
                '-r',
                '200',
                '-f',
                '1',
                '-l',
                (string) config('realitylog.ai.max_ocr_pages', 10),
                $path,
                $prefix,
            ]);
            $process->setTimeout((int) config('realitylog.ai.timeout', 600));
            $process->run();

            if (! $process->isSuccessful()) {
                return [
                    'text' => '',
                    'status' => 'needs_review',
                    'message' => 'This PDF could not be rendered for OCR. The original is preserved.',
                ];
            }

            $pages = glob($prefix.'-*.png') ?: [];
            sort($pages);

            $parts = [];
            $hadFailure = false;

            foreach ($pages as $page) {
                $result = $this->extract($page);

                if ($result['status'] === 'extracted') {
                    $parts[] = $result['text'];
                } elseif ($result['status'] === 'failed') {
                    $hadFailure = true;
                }
            }

            $text = trim(implode("\n\n", array_filter($parts)));

            if ($text === '') {
                return [
                    'text' => '',
                    'status' => $hadFailure ? 'failed' : 'needs_review',
                    'message' => 'No readable text was found in this scanned PDF. Add the key details manually.',
                ];
            }

            return ['text' => $text, 'status' => 'extracted', 'message' => null];
        } catch (Throwable $e) {
            report($e);

            return [
                'text' => '',
                'status' => 'failed',
                'message' => 'Scanned PDF OCR failed. The original file is still safe.',
            ];
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function available(): bool
    {
        return (bool) config('realitylog.ai.enabled') && $this->runtime->tesseract() !== null;
    }

    private function unavailable(string $message): array
    {
        return ['text' => '', 'status' => 'needs_review', 'message' => $message];
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim(Str::limit($text, 200000, ''));
    }
}
