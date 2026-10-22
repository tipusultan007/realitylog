<?php

namespace App\Console\Commands;

use App\Models\Memory;
use App\Services\Ai\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class BackfillEmbeddings extends Command
{
    protected $signature = 'ai:embed
                            {--force : Re-embed memories that already have a vector}
                            {--chunk=25 : Number of memories processed per batch}';

    protected $description = 'Generate local multilingual embeddings for memories';

    public function handle(EmbeddingService $embedding): int
    {
        if (! config('realitylog.ai.enabled')) {
            $this->error('Local AI is disabled. Set LOCAL_AI_ENABLED=true first.');

            return self::FAILURE;
        }

        $embedded = 0;
        $failed = 0;
        $skipped = 0;

        Memory::query()
            ->when(! $this->option('force'), fn ($query) => $query->whereNull('embedded_at'))
            ->orderBy('id')
            ->chunkById(max(1, (int) $this->option('chunk')), function (Collection $memories) use ($embedding, &$embedded, &$failed, &$skipped): void {
                $pending = $memories
                    ->map(fn (Memory $memory): array => [
                        'memory' => $memory,
                        'text' => $embedding->textForMemory($memory),
                    ])
                    ->filter(fn (array $item): bool => $item['text'] !== '')
                    ->values();

                $skipped += $memories->count() - $pending->count();

                if ($pending->isEmpty()) {
                    return;
                }

                try {
                    $vectors = $embedding->embed($pending->pluck('text')->all());
                } catch (Throwable $e) {
                    report($e);
                    $failed += $pending->count();
                    $ids = $pending->pluck('memory.id')->implode(', ');
                    $this->warn("Embedding batch failed for memories {$ids}: ".$e->getMessage());

                    return;
                }

                foreach ($pending as $index => $item) {
                    $vector = $vectors[$index] ?? null;

                    if (! is_array($vector) || $vector === []) {
                        $failed++;
                        $this->warn("No vector returned for memory {$item['memory']->id}.");

                        continue;
                    }

                    $item['memory']->forceFill([
                        'embedding' => $vector,
                        'embedding_model' => (string) config('realitylog.ai.embedding_model'),
                        'embedding_dimensions' => count($vector),
                        'embedded_at' => now(),
                    ])->save();

                    $embedded++;
                }
            });

        $this->info("Embeddings generated: {$embedded}; skipped: {$skipped}; failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
