<?php

namespace App\Jobs;

use App\Models\Memory;
use App\Services\Processing\MemoryProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessMemory implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public int $memoryId) {}

    public function handle(MemoryProcessor $processor): void
    {
        $memory = Memory::query()->find($this->memoryId);

        if (! $memory) {
            return;
        }

        $processor->process($memory);
    }

    public function failed(?Throwable $exception): void
    {
        Memory::query()->whereKey($this->memoryId)->update([
            'processing_status' => 'failed',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Processing failed.', 1000),
        ]);
    }
}
