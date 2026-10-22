<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Models\MemoryRelationship;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Memory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();

        $this->memory = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Original title',
            'memory_type' => 'note',
            'source_type' => 'text',
            'body' => 'Original body',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);
    }

    public function test_the_owner_can_edit_extracted_details_and_tags(): void
    {
        $this->actingAs($this->user)
            ->patch(route('memories.update', $this->memory), [
                'title' => 'Edited title',
                'memory_type' => 'receipt',
                'body' => 'Edited body',
                'extracted_text' => 'Extracted receipt text',
                'summary' => 'A short summary',
                'event_date' => '2026-09-12',
                'detected_language' => 'bn',
                'tags' => 'ফোন, রিসিট, ফোন',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('memories.show', $this->memory));

        $this->memory->refresh();

        $this->assertSame('Edited title', $this->memory->title);
        $this->assertSame('receipt', $this->memory->memory_type);
        $this->assertSame('2026-09-12', $this->memory->event_date->toDateString());
        $this->assertSame('bn', $this->memory->detected_language);
        $this->assertSame(['ফোন', 'রিসিট'], $this->memory->tags->pluck('name')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->user->id, 'action' => 'memory.updated']);
    }

    public function test_editing_only_replaces_tags_belonging_to_the_owner(): void
    {
        $foreignTag = Tag::create(['user_id' => User::factory()->create()->id, 'name' => 'shared', 'color' => '#000000']);

        $this->actingAs($this->user)
            ->patch(route('memories.update', $this->memory), [
                'title' => 'Tag test',
                'memory_type' => 'note',
                'detected_language' => 'en',
                'tags' => 'shared',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tags', ['id' => $foreignTag->id]);
        $this->assertFalse($this->memory->tags()->whereKey($foreignTag->id)->exists());
    }

    public function test_archive_restore_and_favorite_toggle(): void
    {
        $this->actingAs($this->user);

        $this->patch(route('memories.favorite', $this->memory))->assertRedirect();
        $this->assertTrue($this->memory->fresh()->is_favorite);

        $this->patch(route('memories.archive', $this->memory))->assertRedirect(route('memories.index'));
        $this->assertTrue($this->memory->fresh()->is_archived);

        $this->patch(route('memories.restore', $this->memory))->assertRedirect(route('memories.show', $this->memory));
        $this->assertFalse($this->memory->fresh()->is_archived);
    }

    public function test_deleting_a_memory_removes_its_private_attachment(): void
    {
        Storage::disk('local')->put('memories/receipt.txt', 'private');

        $attachment = MemoryAttachment::create([
            'memory_id' => $this->memory->id,
            'disk' => 'local',
            'path' => 'memories/receipt.txt',
            'original_name' => 'receipt.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $this->actingAs($this->user)
            ->delete(route('memories.destroy', $this->memory))
            ->assertRedirect(route('memories.index'));

        $this->assertDatabaseMissing('memories', ['id' => $this->memory->id]);
        $this->assertDatabaseMissing('memory_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing('memories/receipt.txt');
        $this->assertDatabaseHas('audit_logs', ['action' => 'memory.deleted']);
    }

    public function test_memory_detail_suggests_connections_using_similarity_and_shared_entities(): void
    {
        $this->memory->update(['embedding' => [1.0, 0.0, 0.0]]);

        $walton = Entity::create([
            'user_id' => $this->user->id,
            'type' => 'organization',
            'name' => 'Walton',
        ]);

        $this->memory->entities()->attach($walton->id);

        $suggested = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Fridge warranty',
            'memory_type' => 'warranty',
            'source_type' => 'text',
            'body' => 'Warranty for the fridge.',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
            'embedding' => [0.95, 0.05, 0.0],
        ]);
        $suggested->entities()->attach($walton->id);

        $weak = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Trip notes',
            'memory_type' => 'travel',
            'source_type' => 'text',
            'body' => 'A holiday plan.',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
            'embedding' => [0.0, 1.0, 0.0],
        ]);

        $linked = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Fridge receipt',
            'memory_type' => 'receipt',
            'source_type' => 'text',
            'body' => 'The receipt for the fridge.',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
            'embedding' => [0.99, 0.01, 0.0],
        ]);

        MemoryRelationship::create([
            'user_id' => $this->user->id,
            'from_memory_id' => $this->memory->id,
            'to_memory_id' => $linked->id,
            'relationship_type' => 'related',
            'is_confirmed' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('memories.show', $this->memory));

        $response->assertOk();

        $suggestions = $response->viewData('suggestedMemories');
        $suggestedIds = $suggestions->pluck('memory.id')->all();

        $this->assertContains($suggested->id, $suggestedIds);
        $this->assertNotContains($weak->id, $suggestedIds);
        $this->assertNotContains($linked->id, $suggestedIds);

        $match = $suggestions->first(
            fn (array $item): bool => $item['memory']->id === $suggested->id
        );

        $this->assertSame('similarity', $match['reason']);
        $this->assertContains('Walton', $match['shared_entities']);
    }

    public function test_the_owner_can_link_two_memories_but_not_to_a_foreign_memory(): void
    {
        $related = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Related warranty',
            'memory_type' => 'warranty',
            'source_type' => 'text',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);

        $foreign = Memory::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Foreign memory',
            'memory_type' => 'note',
            'source_type' => 'text',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
        ]);

        $this->actingAs($this->user)
            ->post(route('memories.link', $this->memory), [
                'to_memory_id' => $foreign->id,
                'relationship_type' => 'related',
            ])
            ->assertSessionHasErrors('to_memory_id');

        $this->actingAs($this->user)
            ->post(route('memories.link', $this->memory), [
                'to_memory_id' => $related->id,
                'relationship_type' => 'warranty',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('memory_relationships', [
            'user_id' => $this->user->id,
            'from_memory_id' => $this->memory->id,
            'to_memory_id' => $related->id,
            'relationship_type' => 'warranty',
        ]);
    }
}
