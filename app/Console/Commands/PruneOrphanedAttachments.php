<?php

namespace App\Console\Commands;

use App\Models\MemoryAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanedAttachments extends Command
{
    protected $signature = 'attachments:prune-orphans {--dry-run : List orphaned attachments without deleting them}';

    protected $description = 'Delete attachment records and files whose memory no longer exists';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $count = 0;

        MemoryAttachment::query()
            ->doesntHave('memory')
            ->orderBy('id')
            ->chunkById(100, function ($attachments) use ($dryRun, &$count): void {
                foreach ($attachments as $attachment) {
                    if ($dryRun) {
                        $this->line("Would delete attachment #{$attachment->id}: {$attachment->original_name}");
                        $count++;

                        continue;
                    }

                    if ($attachment->disk === 'local' && $attachment->path) {
                        Storage::disk($attachment->disk)->delete($attachment->path);
                    }

                    $attachment->delete();
                    $count++;
                }
            });

        $this->info(($dryRun ? 'Found ' : 'Deleted ')."{$count} orphaned attachment(s).");

        return self::SUCCESS;
    }
}
