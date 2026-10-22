<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        UserSetting::create(['user_id' => $this->user->id]);
        $this->token = $this->plainToken($this->user);
    }

    public function test_multiple_files_can_be_added_to_an_existing_memory(): void
    {
        $memory = $this->createMemory();

        $response = $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $memory),
            [
                'files' => [
                    UploadedFile::fake()->createWithContent(
                        'warranty.txt',
                        'Warranty for the fridge from Walton until 2027-03-01.'
                    ),
                    UploadedFile::fake()->createWithContent(
                        'note.md',
                        'Second document with supporting details.'
                    ),
                ],
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('message', 'Attachments added.')
            ->assertJsonCount(2, 'data.attachments');

        $memory->refresh();

        $this->assertCount(2, $memory->attachments);
        $this->assertStringContainsString('Warranty for the fridge', (string) $memory->extracted_text);
        $this->assertStringContainsString('Second document', (string) $memory->extracted_text);
        $this->assertSame('ready', $memory->processing_status);

        foreach ($memory->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_attachments_cannot_be_added_to_another_users_memory(): void
    {
        $foreignMemory = Memory::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Not yours',
            'memory_type' => 'note',
            'source_type' => 'text',
            'processing_status' => 'ready',
        ]);

        $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $foreignMemory),
            ['files' => [UploadedFile::fake()->createWithContent('note.txt', 'More text.')]],
        )->assertForbidden();

        $this->assertDatabaseCount('memory_attachments', 0);
    }

    public function test_attachment_uploads_are_validated_and_capped(): void
    {
        $memory = $this->createMemory();

        $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $memory),
            ['files' => [UploadedFile::fake()->create('archive.exe', 12)]],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $memory),
            ['files' => array_map(
                fn (int $index) => UploadedFile::fake()->createWithContent("note-{$index}.txt", 'Text'),
                range(1, 9),
            )],
        )->assertUnprocessable()
            ->assertJsonValidationErrors('files');

        $this->assertDatabaseCount('memory_attachments', 0);
    }

    public function test_an_attachment_can_be_deleted_and_the_memory_text_is_rebuilt(): void
    {
        $memory = $this->createMemory();

        $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $memory),
            ['files' => [UploadedFile::fake()->createWithContent('note.txt', 'Deletable extracted text.')]],
        )->assertCreated();

        $attachment = $memory->attachments()->sole();
        $path = $attachment->path;

        $this->withToken($this->token)->deleteJson(
            route('api.v1.memories.attachments.destroy', [$memory, $attachment]),
        )->assertOk()
            ->assertJsonPath('message', 'Attachment removed.')
            ->assertJsonCount(0, 'data.attachments');

        $this->assertDatabaseMissing('memory_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertStringNotContainsString('Deletable extracted text', (string) $memory->fresh()->extracted_text);
    }

    public function test_an_attachment_from_another_memory_cannot_be_deleted_through_a_foreign_memory(): void
    {
        $memory = $this->createMemory();
        $other = $this->createMemory(['title' => 'Other memory']);

        $this->withToken($this->token)->post(
            route('api.v1.memories.attachments.store', $other),
            ['files' => [UploadedFile::fake()->createWithContent('note.txt', 'Other text.')]],
        )->assertCreated();

        $attachment = $other->attachments()->sole();

        $this->withToken($this->token)->deleteJson(
            route('api.v1.memories.attachments.destroy', [$memory, $attachment]),
        )->assertNotFound();

        $this->assertDatabaseHas('memory_attachments', ['id' => $attachment->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMemory(array $overrides = []): Memory
    {
        return Memory::create(array_merge([
            'user_id' => $this->user->id,
            'title' => 'A memory',
            'memory_type' => 'note',
            'source_type' => 'text',
            'processing_status' => 'ready',
            'captured_at' => now(),
        ], $overrides));
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
