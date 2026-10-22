<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Models\User;
use App\Services\Capture\AttachmentService;
use App\Services\Security\FileScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_uploaded_files_are_flagged_as_duplicates(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $memory = $this->createMemory($user);
        $service = app(AttachmentService::class);

        $first = $service->store($memory, UploadedFile::fake()->createWithContent('receipt.txt', 'same file bytes'));
        $second = $service->store($memory, UploadedFile::fake()->createWithContent('receipt-copy.txt', 'same file bytes'));

        $this->assertNull($first->duplicate_of_attachment_id);
        $this->assertSame($first->id, $second->duplicate_of_attachment_id);
        $this->assertSame($first->checksum, $second->checksum);
    }

    public function test_a_rejected_file_is_not_persisted(): void
    {
        Storage::fake('local');

        $this->app->bind(FileScanner::class, fn (): FileScanner => new class implements FileScanner
        {
            public function scan(UploadedFile $file): void
            {
                throw ValidationException::withMessages(['file' => 'infected']);
            }
        });

        $user = User::factory()->create();
        $memory = $this->createMemory($user);

        try {
            app(AttachmentService::class)->store(
                $memory,
                UploadedFile::fake()->createWithContent('receipt.txt', 'unsafe bytes'),
            );

            $this->fail('The scanner should have rejected the file.');
        } catch (ValidationException) {
            $this->assertSame(0, MemoryAttachment::count());
        }
    }

    private function createMemory(User $user): Memory
    {
        return Memory::create([
            'user_id' => $user->id,
            'title' => 'A memory',
            'memory_type' => 'note',
            'source_type' => 'text',
            'processing_status' => 'ready',
            'captured_at' => now(),
        ]);
    }
}
