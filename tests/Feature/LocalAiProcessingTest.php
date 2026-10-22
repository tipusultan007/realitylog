<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Services\Ai\EmbeddingService;
use App\Services\Ai\OcrService;
use App\Services\Ai\TranscriptionService;
use App\Services\Processing\MemoryProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class LocalAiProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_an_image_is_ocrd_and_its_text_becomes_searchable(): void
    {
        $ocr = Mockery::mock(OcrService::class);
        $ocr->shouldReceive('extract')
            ->once()
            ->andReturn([
                'text' => 'Walton fridge receipt BDT 18,000',
                'status' => 'extracted',
                'message' => null,
            ]);
        $this->app->instance(OcrService::class, $ocr);

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'image',
                'file' => UploadedFile::fake()->image('receipt.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $memory = Memory::sole();

        $this->assertSame('ready', $memory->processing_status);
        $this->assertSame('extracted', $memory->attachments()->sole()->status);
        $this->assertStringContainsString('Walton fridge receipt', (string) $memory->extracted_text);
        $this->assertDatabaseHas('processing_jobs', [
            'memory_id' => $memory->id,
            'type' => 'extract_text',
            'status' => 'extracted',
        ]);
    }

    public function test_an_audio_memory_is_transcribed_and_preserves_its_original(): void
    {
        $transcription = Mockery::mock(TranscriptionService::class);
        $transcription->shouldReceive('transcribe')
            ->once()
            ->andReturn([
                'text' => 'I bought a phone from Star Tech for 32500 taka',
                'status' => 'extracted',
                'message' => null,
                'language' => 'en',
            ]);
        $this->app->instance(TranscriptionService::class, $transcription);

        $this->actingAs($this->user)
            ->post(route('capture.store'), [
                'source_type' => 'voice',
                'file' => UploadedFile::fake()->create('voice.mp3', 128, 'audio/mpeg'),
            ])
            ->assertSessionHasNoErrors();

        $memory = Memory::sole();

        $this->assertSame('ready', $memory->processing_status);
        $this->assertStringContainsString('I bought a phone', (string) $memory->extracted_text);
        Storage::disk('local')->assertExists($memory->attachments()->sole()->path);
    }

    public function test_a_memory_gets_a_local_embedding_after_processing(): void
    {
        config([
            'realitylog.ai.enabled' => true,
            'realitylog.ai.embedding_model' => 'test-multilingual-model',
        ]);

        $embedding = Mockery::mock(EmbeddingService::class);
        $embedding->shouldReceive('embedMemory')
            ->once()
            ->andReturn(array_fill(0, 384, 0.01));
        $this->app->instance(EmbeddingService::class, $embedding);

        $memory = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'Phone receipt',
            'body' => 'I bought a phone from Star Tech for 32500 taka.',
            'source_type' => 'text',
            'memory_type' => 'note',
        ]);

        app(MemoryProcessor::class)->process($memory);
        $memory->refresh();

        $this->assertCount(384, $memory->embedding);
        $this->assertSame('test-multilingual-model', $memory->embedding_model);
        $this->assertSame(384, $memory->embedding_dimensions);
        $this->assertNotNull($memory->embedded_at);
        $this->assertDatabaseHas('processing_jobs', [
            'memory_id' => $memory->id,
            'type' => 'embed',
            'status' => 'completed',
        ]);
    }

    public function test_an_embedding_failure_does_not_lose_the_memory(): void
    {
        config([
            'realitylog.ai.enabled' => true,
            'realitylog.ai.embedding_model' => 'test-multilingual-model',
        ]);

        $embedding = Mockery::mock(EmbeddingService::class);
        $embedding->shouldReceive('embedMemory')
            ->once()
            ->andThrow(new RuntimeException('embedding model unavailable'));
        $this->app->instance(EmbeddingService::class, $embedding);

        $memory = Memory::create([
            'user_id' => $this->user->id,
            'title' => 'A note that must survive',
            'body' => 'The original text is still useful without a vector.',
            'source_type' => 'text',
            'memory_type' => 'note',
        ]);

        app(MemoryProcessor::class)->process($memory);
        $memory->refresh();

        $this->assertSame('ready', $memory->processing_status);
        $this->assertNull($memory->embedding);
        $this->assertDatabaseHas('processing_jobs', [
            'memory_id' => $memory->id,
            'type' => 'embed',
            'status' => 'failed',
        ]);
    }
}
