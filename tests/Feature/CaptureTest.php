<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    public function test_a_text_memory_is_captured_processed_and_audited(): void
    {
        $response = $this->actingAs($this->user)->post(route('capture.store'), [
            'source_type' => 'text',
            'title' => 'নতুন ফোনের রিসিট',
            'body' => 'স্টার টেক থেকে ফোন কিনেছি। দাম ৩২,৫০০ টাকা।',
            'tags' => 'ফোন, রিসিট, ফোন',
            'event_date' => now()->subDay()->toDateString(),
            'language' => 'bn',
        ]);

        $memory = Memory::sole();

        $response->assertRedirect(route('memories.show', $memory));

        $this->assertSame($this->user->id, $memory->user_id);
        $this->assertSame('receipt', $memory->memory_type);
        $this->assertSame('bn', $memory->detected_language);
        $this->assertSame('ready', $memory->processing_status);
        $this->assertSame('BDT 32,500', $memory->amount());
        $this->assertCount(2, $memory->tags);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $this->user->id, 'action' => 'memory.created']);
        $this->assertDatabaseHas('processing_jobs', ['memory_id' => $memory->id, 'type' => 'classify', 'status' => 'completed']);
    }

    public function test_a_link_memory_requires_and_stores_the_url(): void
    {
        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'link',
                'title' => 'Padma Bridge article',
                'source_url' => 'https://example.com/padma-bridge',
            ])
            ->assertSessionHasNoErrors();

        $memory = Memory::sole();

        $this->assertSame('link', $memory->memory_type);
        $this->assertSame('https://example.com/padma-bridge', $memory->source_url);
    }

    public function test_a_document_upload_is_stored_privately_and_its_text_is_extracted(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'warranty.txt',
            'Warranty for the fridge from Walton. BDT 18,000. Warranty until 2027-03-01.'
        );

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'document',
                'file' => $file,
                'language' => 'en',
            ])
            ->assertSessionHasNoErrors();

        $memory = Memory::sole();
        $attachment = $memory->attachments()->sole();

        $this->assertSame('local', $attachment->disk);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('extracted', $attachment->status);
        $this->assertStringContainsString('Warranty for the fridge', $memory->extracted_text);
        $this->assertSame('ready', $memory->processing_status);
        $this->assertSame('Walton', $memory->ai_metadata['merchant']);
    }

    public function test_capture_validation_rejects_empty_notes_broken_links_and_foreign_collections(): void
    {
        $foreignCollection = Collection::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Someone else',
        ]);

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'text',
                'title' => '',
                'body' => '',
            ])
            ->assertSessionHasErrors('body');

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'link',
                'title' => 'Broken link',
                'source_url' => 'not-a-url',
            ])
            ->assertSessionHasErrors('source_url');

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'text',
                'body' => 'A valid note.',
                'collection_id' => $foreignCollection->id,
            ])
            ->assertSessionHasErrors('collection_id');

        $this->assertDatabaseCount('memories', 0);
    }

    public function test_image_upload_without_ocr_is_preserved_for_review(): void
    {
        $file = UploadedFile::fake()->image('receipt.jpg');

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'image',
                'file' => $file,
            ])
            ->assertSessionHasNoErrors();

        $memory = Memory::sole();

        $this->assertSame('needs_review', $memory->processing_status);
        $this->assertSame('needs_review', $memory->attachments()->sole()->status);
        $this->assertNotNull($memory->processing_error);
        Storage::disk('local')->assertExists($memory->attachments()->sole()->path);
    }
}
