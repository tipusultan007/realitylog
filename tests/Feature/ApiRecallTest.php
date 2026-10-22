<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Ai\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ApiRecallTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        UserSetting::create([
            'user_id' => $this->user->id,
            'interface_locale' => 'en',
            'ai_locale' => 'bn',
        ]);

        $this->token = $this->plainToken($this->user);
    }

    public function test_recall_answers_in_the_answer_locale_and_cites_the_source(): void
    {
        $receipt = $this->createMemory([
            'title' => 'নতুন ফোনের রিসিট',
            'body' => 'স্টার টেক থেকে ফোন কিনেছিলাম। দাম ৩২,৫০০ টাকা।',
            'detected_language' => 'bn',
            'memory_type' => 'receipt',
            'ai_metadata' => ['amount' => 'BDT 32,500', 'merchant' => 'Star Tech'],
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query([
                'q' => 'ফোনের রিসিট কোন দোকান থেকে কিনেছিলাম',
            ]));

        $response->assertOk()
            ->assertJsonPath('has_answer', true)
            ->assertJsonPath('locale', 'bn')
            ->assertJsonPath('sources.0.memory.id', $receipt->id);

        $this->assertStringContainsString('Star Tech', $response->json('answer'));
        $this->assertNotEmpty($response->json('sources.0.excerpt'));
    }

    public function test_unrelated_questions_return_no_fabricated_answer(): void
    {
        $this->createMemory(['title' => 'Phone receipt from Star Tech']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query(['q' => 'quantum entanglement experiments']));

        $response->assertOk()
            ->assertJsonPath('has_answer', false)
            ->assertJsonPath('answer', null)
            ->assertJsonPath('total', 0)
            ->assertJsonPath('sources', []);
    }

    public function test_search_filters_exclude_archived_records_unless_requested(): void
    {
        $this->createMemory([
            'title' => 'Old phone receipt',
            'is_archived' => true,
        ]);

        $hidden = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query(['q' => 'phone receipt']));

        $hidden->assertOk()->assertJsonPath('total', 0);

        $visible = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query([
                'q' => 'phone receipt',
                'archived' => 1,
            ]));

        $visible->assertOk()->assertJsonPath('total', 1);
    }

    public function test_a_purchase_list_question_returns_multiple_cited_records(): void
    {
        foreach ([
            ['title' => 'Laptop purchase', 'body' => 'Bought a laptop.', 'days' => 5],
            ['title' => 'Phone purchase', 'body' => 'Bought a phone.', 'days' => 20],
            ['title' => 'Desk purchase', 'body' => 'Bought a desk.', 'days' => 40],
        ] as $purchase) {
            $this->createMemory([
                'title' => $purchase['title'],
                'body' => $purchase['body'],
                'memory_type' => 'purchase',
                'event_date' => now()->subDays($purchase['days'])->toDateString(),
            ]);
        }

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query(['q' => 'my last three purchases']));

        $response->assertOk()
            ->assertJsonPath('has_answer', true)
            ->assertJsonPath('answer_kind', 'list')
            ->assertJsonCount(3, 'sources');

        $this->assertStringContainsString('Laptop purchase', $response->json('answer'));
        $this->assertStringContainsString('Phone purchase', $response->json('answer'));
        $this->assertStringContainsString('Desk purchase', $response->json('answer'));
    }

    public function test_the_query_is_saved_in_search_history(): void
    {
        $this->createMemory(['title' => 'Phone receipt']);

        $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query(['q' => 'phone receipt']))
            ->assertOk();

        $this->assertDatabaseHas('search_histories', [
            'user_id' => $this->user->id,
            'query' => 'phone receipt',
            'locale' => 'en',
        ]);
    }

    public function test_semantic_recall_exposes_similarity_and_the_vector_engine(): void
    {
        config()->set('realitylog.ai.enabled', true);

        $target = $this->createMemory([
            'title' => 'Fridge compressor repair',
            'body' => 'The appliance was repaired at home.',
        ]);
        $target->update(['embedding' => [1.0, 0.0, 0.0], 'embedded_at' => now()]);

        $this->mock(EmbeddingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('available')->once()->andReturnTrue();
            $mock->shouldReceive('embed')
                ->once()
                ->with(['device guarantee ending'])
                ->andReturn([[1.0, 0.0, 0.0]]);
        });

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/search?'.http_build_query(['q' => 'device guarantee ending']));

        $response->assertOk()
            ->assertJsonPath('semantic_engine', 'local_embeddings')
            ->assertJsonPath('results.0.match_type', 'semantic')
            ->assertJsonPath('results.0.vector_match', true)
            ->assertJsonPath('results.0.memory.id', $target->id)
            ->assertJsonPath('sources.0.similarity', 1)
            ->assertJsonPath('sources.0.vector_match', true);

        $this->assertEqualsWithDelta(1.0, $response->json('results.0.similarity'), 0.0001);
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
            'detected_language' => $attributes['detected_language'] ?? 'en',
            'event_date' => $attributes['event_date'] ?? null,
            'captured_at' => $attributes['captured_at'] ?? now(),
            'processing_status' => 'ready',
            'ai_metadata' => $attributes['ai_metadata'] ?? null,
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
