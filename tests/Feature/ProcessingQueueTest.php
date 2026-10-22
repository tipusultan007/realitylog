<?php

namespace Tests\Feature;

use App\Jobs\ProcessMemory;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessingQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_dispatches_processing_to_the_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('capture.store'), [
            'source_type' => 'text',
            'title' => 'A queued memory',
            'body' => 'This memory should be processed by a worker.',
            'language' => 'en',
        ])->assertRedirect();

        $memory = Memory::sole();

        Queue::assertPushed(ProcessMemory::class, fn (ProcessMemory $job): bool => $job->memoryId === $memory->id);
        $this->assertSame('queued', $memory->processing_status);
    }

    public function test_reprocess_dispatches_the_job_again(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $memory = Memory::create([
            'user_id' => $user->id,
            'title' => 'Reprocess me',
            'memory_type' => 'note',
            'source_type' => 'text',
            'processing_status' => 'ready',
            'captured_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('memories.reprocess', $memory))
            ->assertRedirect();

        Queue::assertPushed(ProcessMemory::class, fn (ProcessMemory $job): bool => $job->memoryId === $memory->id);
    }
}
