<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class TranscriptionService
{
    public function __construct(private readonly AiRuntime $runtime) {}

    /**
     * @return array{text: string, status: string, message: ?string, language: ?string}
     */
    public function transcribe(string $path): array
    {
        if (! config('realitylog.ai.enabled')) {
            return $this->unavailable('Local transcription is disabled on this server.');
        }

        if (! is_file($path)) {
            return ['text' => '', 'status' => 'failed', 'message' => 'The audio file could not be read.', 'language' => null];
        }

        $temporaryDirectory = null;

        try {
            $input = $path;
            $ffmpeg = $this->runtime->ffmpeg();

            if ($ffmpeg !== null) {
                $temporaryDirectory = $this->runtime->temporaryDirectory('audio');
                $temporary = $temporaryDirectory.DIRECTORY_SEPARATOR.'audio.wav';

                $convert = new Process([
                    $ffmpeg,
                    '-y',
                    '-i',
                    $path,
                    '-ac',
                    '1',
                    '-ar',
                    '16000',
                    $temporary,
                ]);
                $convert->setTimeout((int) config('realitylog.ai.timeout', 600));
                $convert->run();

                if ($convert->isSuccessful()) {
                    $input = $temporary;
                }
            }

            $result = $this->runtime->runPython('transcribe.py', [
                '--path',
                $input,
                '--model',
                (string) config('realitylog.ai.whisper_model', 'base'),
                '--device',
                (string) config('realitylog.ai.whisper_device', 'cpu'),
                '--compute-type',
                (string) config('realitylog.ai.whisper_compute_type', 'int8'),
            ]);

            $text = $this->clean((string) ($result['text'] ?? ''));

            if ($text === '') {
                return [
                    'text' => '',
                    'status' => 'needs_review',
                    'message' => 'No speech was detected in this audio file. Add the key details manually.',
                    'language' => isset($result['language']) ? (string) $result['language'] : null,
                ];
            }

            return [
                'text' => $text,
                'status' => 'extracted',
                'message' => null,
                'language' => isset($result['language']) ? (string) $result['language'] : null,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'text' => '',
                'status' => 'failed',
                'message' => 'Transcription failed. The original audio is still safe.',
                'language' => null,
            ];
        } finally {
            if ($temporaryDirectory !== null) {
                File::deleteDirectory($temporaryDirectory);
            }
        }
    }

    public function available(): bool
    {
        return (bool) config('realitylog.ai.enabled') && $this->runtime->python() !== null;
    }

    private function unavailable(string $message): array
    {
        return ['text' => '', 'status' => 'needs_review', 'message' => $message, 'language' => null];
    }

    private function clean(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim(Str::limit($text, 200000, ''));
    }
}
