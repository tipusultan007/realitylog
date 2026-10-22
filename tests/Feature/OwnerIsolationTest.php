<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwnerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $intruder;

    private Memory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->owner = User::factory()->create();
        $this->intruder = User::factory()->create();

        $this->memory = $this->createMemory($this->owner, 'Owner private receipt');
    }

    public function test_other_users_cannot_view_edit_update_archive_or_delete_a_memory(): void
    {
        $this->actingAs($this->intruder);

        $this->get(route('memories.show', $this->memory))->assertForbidden();
        $this->get(route('memories.edit', $this->memory))->assertForbidden();
        $this->patch(route('memories.update', $this->memory), ['title' => 'Hijacked'])->assertForbidden();
        $this->patch(route('memories.archive', $this->memory))->assertForbidden();
        $this->patch(route('memories.favorite', $this->memory))->assertForbidden();
        $this->delete(route('memories.destroy', $this->memory))->assertForbidden();

        $this->assertDatabaseHas('memories', [
            'id' => $this->memory->id,
            'title' => 'Owner private receipt',
            'is_archived' => false,
        ]);
    }

    public function test_attachments_are_only_readable_by_the_owner(): void
    {
        Storage::disk('local')->put('memories/receipt.txt', 'private receipt contents');

        $attachment = MemoryAttachment::create([
            'memory_id' => $this->memory->id,
            'disk' => 'local',
            'path' => 'memories/receipt.txt',
            'original_name' => 'receipt.txt',
            'mime_type' => 'text/plain',
            'size' => 24,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $this->actingAs($this->owner);
        $this->get(route('attachments.show', $attachment))->assertOk();
        $this->get(route('attachments.download', $attachment))->assertOk();

        $this->actingAs($this->intruder);
        $this->get(route('attachments.show', $attachment))->assertForbidden();
        $this->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_search_and_export_never_include_another_users_memories(): void
    {
        $otherMemory = $this->createMemory($this->intruder, 'Intruder secret diary');

        $this->actingAs($this->owner);

        $search = $this->get(route('search.index', ['q' => 'intruder secret diary']));
        $search->assertOk();
        $search->assertDontSee('Intruder secret diary');

        $export = $this->get(route('export'));
        $export->assertOk();

        $payload = $export->streamedContent();

        $this->assertStringContainsString('Owner private receipt', $payload);
        $this->assertStringNotContainsString('Intruder secret diary', $payload);
        $this->assertStringNotContainsString($this->intruder->email, $payload);
        $this->assertStringNotContainsString($otherMemory->title, $payload);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('memories.show', $this->memory))->assertRedirect(route('login'));
        $this->get(route('export'))->assertRedirect(route('login'));
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    private function createMemory(User $user, string $title): Memory
    {
        return Memory::create([
            'user_id' => $user->id,
            'title' => $title,
            'memory_type' => 'receipt',
            'source_type' => 'text',
            'body' => 'A private record.',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);
    }
}
