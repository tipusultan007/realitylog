<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Collection;
use App\Models\Entity;
use App\Models\Memory;
use App\Models\MemoryAttachment;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiMemoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        UserSetting::create([
            'user_id' => $this->user->id,
            'interface_locale' => 'en',
            'ai_locale' => 'en',
        ]);

        $this->token = $this->plainToken($this->user);
    }

    public function test_the_library_supports_search_filters_modes_and_pagination(): void
    {
        $receipt = $this->createMemory([
            'title' => 'iPhone receipt',
            'memory_type' => 'receipt',
            'body' => 'Bought a phone from Star Tech.',
            'event_date' => now()->subDay()->toDateString(),
        ]);

        $this->createMemory([
            'title' => 'Archived note',
            'memory_type' => 'note',
            'is_archived' => true,
        ]);

        $this->createMemory([
            'title' => 'Another receipt',
            'memory_type' => 'receipt',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/memories?mode=library&type=receipt&q=iPhone&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $receipt->id)
            ->assertJsonPath('filters.type', 'receipt')
            ->assertJsonPath('total_for_mode', 2);

        $this->withToken($this->token)
            ->getJson('/api/v1/memories?mode=archive')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Archived note');
    }

    public function test_memory_detail_returns_extracted_data_attachments_and_related_records(): void
    {
        $memory = $this->createMemory([
            'title' => 'Phone warranty',
            'memory_type' => 'warranty',
            'summary' => 'Warranty until 2027.',
            'ai_metadata' => ['merchant' => 'Star Tech', 'amount' => 'BDT 132,999'],
        ]);

        Storage::disk('local')->put('memories/'.$this->user->id.'/warranty.pdf', 'pdf-content');

        MemoryAttachment::create([
            'memory_id' => $memory->id,
            'disk' => 'local',
            'path' => 'memories/'.$this->user->id.'/warranty.pdf',
            'original_name' => 'warranty.pdf',
            'mime_type' => 'application/pdf',
            'size' => 11,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $related = $this->createMemory(['title' => 'iPhone receipt', 'memory_type' => 'receipt']);

        $this->withToken($this->token)
            ->postJson('/api/v1/memories/'.$memory->id.'/link', [
                'to_memory_id' => $related->id,
                'relationship_type' => 'warranty',
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->getJson('/api/v1/memories/'.$memory->id)
            ->assertOk()
            ->assertJsonPath('data.amount', 'BDT 132,999')
            ->assertJsonPath('data.attachments.0.original_name', 'warranty.pdf')
            ->assertJsonPath('data.attachments.0.download_url', '/api/v1/attachments/1/download')
            ->assertJsonPath('related.0.id', $related->id)
            ->assertJsonPath('related.0.relationship_type', 'warranty')
            ->assertJsonStructure(['data' => ['extracted_text'], 'linkable', 'related' => [['relationship_id']]]);
    }

    public function test_the_owner_can_edit_favorite_archive_and_restore(): void
    {
        $memory = $this->createMemory(['title' => 'Original title']);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id, [
                'title' => 'Edited title',
                'memory_type' => 'receipt',
                'body' => 'Edited body',
                'extracted_text' => 'Extracted text',
                'summary' => 'Edited summary',
                'event_date' => '2026-09-12',
                'detected_language' => 'bn',
                'tags' => 'ফোন, রিসিট, ফোন',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Edited title')
            ->assertJsonPath('data.detected_language', 'bn');

        $memory->refresh();

        $this->assertSame(['ফোন', 'রিসিট'], $memory->tags->pluck('name')->sort()->values()->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'memory.updated']);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', true);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id.'/archive')
            ->assertOk()
            ->assertJsonPath('is_archived', true);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id.'/restore')
            ->assertOk()
            ->assertJsonPath('is_archived', false);
    }

    public function test_memory_collections_are_synced_when_editing(): void
    {
        $memory = $this->createMemory(['title' => 'Fridge warranty']);

        $home = Collection::create([
            'user_id' => $this->user->id,
            'name' => 'Home',
            'color' => '#D95F3B',
        ]);

        $docs = Collection::create([
            'user_id' => $this->user->id,
            'name' => 'Documents',
            'color' => '#517A64',
        ]);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id, [
                'title' => 'Fridge warranty',
                'memory_type' => 'warranty',
                'detected_language' => 'en',
                'collection_ids' => [$home->id, $docs->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.collections');

        $this->assertSame(
            [$home->id, $docs->id],
            $memory->collections()->orderBy('collections.id')->pluck('collections.id')->all()
        );

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id, [
                'title' => 'Fridge warranty',
                'memory_type' => 'warranty',
                'detected_language' => 'en',
                'collection_ids' => [$home->id],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.collections');

        $foreign = Collection::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Foreign',
            'color' => '#D95F3B',
        ]);

        $this->withToken($this->token)
            ->patchJson('/api/v1/memories/'.$memory->id, [
                'title' => 'Fridge warranty',
                'memory_type' => 'warranty',
                'detected_language' => 'en',
                'collection_ids' => [$foreign->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('collection_ids.0');
    }

    public function test_a_memory_link_can_be_removed_only_by_its_owner(): void
    {
        $memory = $this->createMemory(['title' => 'Phone warranty']);
        $related = $this->createMemory(['title' => 'Phone receipt']);

        $relationshipId = $this->withToken($this->token)
            ->postJson('/api/v1/memories/'.$memory->id.'/link', [
                'to_memory_id' => $related->id,
                'relationship_type' => 'warranty',
            ])
            ->assertCreated()
            ->json('relationship_id');

        $intruder = User::factory()->create();

        $this->withToken($this->plainToken($intruder))
            ->deleteJson('/api/v1/memories/'.$memory->id.'/links/'.$relationshipId)
            ->assertForbidden();

        $this->assertDatabaseHas('memory_relationships', ['id' => $relationshipId]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/memories/'.$memory->id.'/links/'.$relationshipId)
            ->assertOk();

        $this->assertDatabaseMissing('memory_relationships', ['id' => $relationshipId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'memory.unlinked']);
    }

    public function test_deleting_a_memory_removes_private_files_and_audits_the_action(): void
    {
        $memory = $this->createMemory(['title' => 'Delete me']);
        Storage::disk('local')->put('memories/'.$this->user->id.'/delete.txt', 'private');

        $attachment = MemoryAttachment::create([
            'memory_id' => $memory->id,
            'disk' => 'local',
            'path' => 'memories/'.$this->user->id.'/delete.txt',
            'original_name' => 'delete.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/memories/'.$memory->id)
            ->assertOk();

        $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
        $this->assertDatabaseMissing('memory_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'memory.deleted']);
    }

    public function test_other_users_cannot_read_or_change_records_or_attachments(): void
    {
        $intruder = User::factory()->create();
        $intruderToken = $this->plainToken($intruder);
        $memory = $this->createMemory(['title' => 'Private record']);

        Storage::disk('local')->put('memories/'.$this->user->id.'/private.txt', 'private');

        $attachment = MemoryAttachment::create([
            'memory_id' => $memory->id,
            'disk' => 'local',
            'path' => 'memories/'.$this->user->id.'/private.txt',
            'original_name' => 'private.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $this->withToken($intruderToken)
            ->getJson('/api/v1/memories/'.$memory->id)
            ->assertForbidden();

        $this->withToken($intruderToken)
            ->patchJson('/api/v1/memories/'.$memory->id, [
                'title' => 'Hacked',
                'memory_type' => 'note',
                'detected_language' => 'en',
            ])
            ->assertForbidden();

        $this->withToken($intruderToken)
            ->deleteJson('/api/v1/memories/'.$memory->id)
            ->assertForbidden();

        $this->withToken($intruderToken)
            ->get('/api/v1/attachments/'.$attachment->id)
            ->assertForbidden();

        $this->withHeaders(['Authorization' => ''])
            ->get('/api/v1/attachments/'.$attachment->id)
            ->assertUnauthorized();
    }

    public function test_a_text_memory_can_be_captured_through_the_api(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/capture', [
                'source_type' => 'text',
                'title' => 'নতুন ফোনের রিসিট',
                'body' => 'স্টার টেক থেকে ফোন কিনেছি। দাম ৩২,৫০০ টাকা।',
                'tags' => 'ফোন, রিসিট',
                'language' => 'bn',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.detected_language', 'bn')
            ->assertJsonPath('data.memory_type', 'receipt');

        $memory = Memory::sole();

        $this->assertSame($this->user->id, $memory->user_id);
        $this->assertSame('BDT 32,500', $memory->amount());
        $this->assertSame('ready', $memory->processing_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'memory.created']);
    }

    public function test_a_document_upload_is_stored_privately_and_extracted(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'warranty.txt',
            'Warranty for the fridge from Walton. BDT 18,000. Warranty until 2027-03-01.'
        );

        $response = $this->withToken($this->token)
            ->post('/api/v1/capture', [
                'source_type' => 'document',
                'file' => $file,
                'language' => 'en',
            ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $memory = Memory::sole();

        $this->assertSame('Walton', $memory->ai_metadata['merchant']);
        $this->assertStringContainsString('Warranty for the fridge', $memory->extracted_text);
        $this->assertSame('local', $memory->attachments()->sole()->disk);
        Storage::disk('local')->assertExists($memory->attachments()->sole()->path);
    }

    public function test_attachment_content_is_available_to_the_owner_with_a_bearer_token(): void
    {
        $memory = $this->createMemory(['title' => 'Private file']);
        Storage::disk('local')->put('memories/'.$this->user->id.'/receipt.txt', 'private');

        $attachment = MemoryAttachment::create([
            'memory_id' => $memory->id,
            'disk' => 'local',
            'path' => 'memories/'.$this->user->id.'/receipt.txt',
            'original_name' => 'receipt.txt',
            'mime_type' => 'text/plain',
            'size' => 7,
            'kind' => 'document',
            'status' => 'stored',
        ]);

        $this->withToken($this->token)
            ->get('/api/v1/attachments/'.$attachment->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->withToken($this->token)
            ->get('/api/v1/attachments/'.$attachment->id.'/download')
            ->assertDownload('receipt.txt');
    }

    public function test_dashboard_returns_owner_scoped_stats_and_recent_records(): void
    {
        $this->createMemory(['title' => 'Recent note']);
        $this->createMemory(['title' => 'Favourite receipt', 'is_favorite' => true]);
        $this->createMemory(['title' => 'Archived note', 'is_archived' => true]);

        ApiToken::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'other',
            'token_hash' => hash('sha256', 'rn_'.Str::random(40)),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.memories', 2)
            ->assertJsonPath('stats.favorites', 1)
            ->assertJsonCount(2, 'recent_memories');
    }

    public function test_memory_detail_returns_suggested_connections_with_evidence(): void
    {
        $memory = $this->createMemory(['title' => 'Fridge repair']);
        $memory->update(['embedding' => [1.0, 0.0, 0.0]]);

        $walton = Entity::create([
            'user_id' => $this->user->id,
            'type' => 'organization',
            'name' => 'Walton',
        ]);
        $memory->entities()->attach($walton->id);

        $suggested = $this->createMemory([
            'title' => 'Fridge warranty',
            'memory_type' => 'warranty',
        ]);
        $suggested->update(['embedding' => [0.95, 0.05, 0.0]]);
        $suggested->entities()->attach($walton->id);

        $this->withToken($this->token)
            ->getJson('/api/v1/memories/'.$memory->id)
            ->assertOk()
            ->assertJsonPath('suggested.0.memory.id', $suggested->id)
            ->assertJsonPath('suggested.0.reason', 'similarity')
            ->assertJsonPath('suggested.0.shared_entities.0', 'Walton')
            ->assertJsonStructure([
                'suggested' => [[
                    'memory' => ['id', 'title'],
                    'score',
                    'similarity',
                    'reason',
                    'shared_entities',
                    'shared_tags',
                ]],
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMemory(array $attributes): Memory
    {
        return Memory::create([
            'user_id' => $this->user->id,
            'title' => $attributes['title'],
            'memory_type' => $attributes['memory_type'] ?? 'note',
            'source_type' => 'text',
            'body' => $attributes['body'] ?? null,
            'summary' => $attributes['summary'] ?? null,
            'detected_language' => $attributes['detected_language'] ?? 'en',
            'event_date' => $attributes['event_date'] ?? null,
            'captured_at' => $attributes['captured_at'] ?? now(),
            'processing_status' => 'ready',
            'ai_metadata' => $attributes['ai_metadata'] ?? null,
            'is_favorite' => $attributes['is_favorite'] ?? false,
            'is_archived' => $attributes['is_archived'] ?? false,
        ]);
    }

    private function plainToken(User $user): string
    {
        $plain = 'rl_'.Str::random(40);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDay(),
        ]);

        return $plain;
    }
}
