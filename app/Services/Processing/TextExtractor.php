<?php

namespace App\Services\Processing;

use App\Models\MemoryAttachment;
use App\Services\Ai\OcrService;
use App\Services\Ai\TranscriptionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

class TextExtractor
{
    public function __construct(
        private readonly OcrService $ocr,
        private readonly TranscriptionService $transcription,
    ) {}

    /**
     * Extract readable text from a stored attachment.
     *
     * @return array{text: string, status: string, message: ?string}
     */
    public function extract(MemoryAttachment $attachment): array
    {
        try {
            $mime = (string) $attachment->mime_type;
            $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));

            if (str_starts_with($mime, 'text/') || in_array($extension, ['txt', 'md', 'csv'], true)) {
                return [
                    'text' => $this->clean(Storage::disk($attachment->disk)->get($attachment->path) ?? ''),
                    'status' => 'extracted',
                    'message' => null,
                ];
            }

            if ($mime === 'application/pdf' || $extension === 'pdf') {
                return $this->extractPdf($attachment);
            }

            if ($extension === 'docx') {
                return $this->extractDocx($attachment);
            }

            if ($extension === 'doc') {
                return [
                    'text' => '',
                    'status' => 'needs_review',
                    'message' => __('ui.capture.needs_review_hint'),
                ];
            }

            if (str_starts_with($mime, 'image/')) {
                return $this->ocr->extract($this->storedPath($attachment));
            }

            if (str_starts_with($mime, 'audio/')) {
                $result = $this->transcription->transcribe($this->storedPath($attachment));

                return [
                    'text' => $result['text'],
                    'status' => $result['status'],
                    'message' => $result['message'],
                ];
            }

            return [
                'text' => '',
                'status' => 'needs_review',
                'message' => 'This file type cannot be read automatically. The original is preserved.',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'text' => '',
                'status' => 'failed',
                'message' => 'Text extraction failed. The original file is still safe.',
            ];
        }
    }

    private function extractPdf(MemoryAttachment $attachment): array
    {
        $text = '';

        try {
            $parser = new Parser;
            $pdf = $parser->parseContent(Storage::disk($attachment->disk)->get($attachment->path));
            $text = $this->clean($pdf->getText());
        } catch (Throwable $e) {
            report($e);
        }

        if (mb_strlen($text) < 12) {
            return $this->ocr->extractPdf($this->storedPath($attachment));
        }

        return ['text' => $text, 'status' => 'extracted', 'message' => null];
    }

    private function extractDocx(MemoryAttachment $attachment): array
    {
        $path = Storage::disk($attachment->disk)->path($attachment->path);
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [
                'text' => '',
                'status' => 'failed',
                'message' => 'The document could not be opened.',
            ];
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $xml) {
            return ['text' => '', 'status' => 'failed', 'message' => 'No readable text was found.'];
        }

        $withBreaks = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $text = html_entity_decode(strip_tags((string) $withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'text' => $this->clean($text),
            'status' => 'extracted',
            'message' => null,
        ];
    }

    private function storedPath(MemoryAttachment $attachment): string
    {
        return Storage::disk($attachment->disk)->path($attachment->path);
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim(Str::limit($text, 200000, ''));
    }
}
