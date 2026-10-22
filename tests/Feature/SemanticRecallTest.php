<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Ai\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SemanticRecallTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        UserSetting::create([
            'user_id' => $this->user->id,
            'interface_locale' => 'en',
            'ai_locale' => 'en',
        ]);
    }

    public function test_a_strong_vector_match_is_returned_with_its_similarity(): void
    {
        config()->set('realitylog.ai.enabled', true);

        $target = $this->createMemory([
            'title' => 'Fridge compressor repair',
            'body' => 'The appliance was repaired at home.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $this->createMemory([
            'title' => 'Eid trip notes',
            'body' => 'Travel plans for the holiday.',
            'embedding' => [0.0, 1.0, 0.0],
        ]);

        $this->mock(EmbeddingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('available')->once()->andReturnTrue();
            $mock->shouldReceive('embed')
                ->once()
                ->with(['device guarantee ending'])
                ->andReturn([[1.0, 0.0, 0.0]]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'device guarantee ending']));

        $result = $this->recallResult($response);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['results']);
        $this->assertSame($target->id, $result['results'][0]['memory']->id);
        $this->assertSame('semantic', $result['results'][0]['match_type']);
        $this->assertTrue($result['results'][0]['vector_match']);
        $this->assertEqualsWithDelta(1.0, $result['results'][0]['similarity'], 0.0001);
        $this->assertSame('local_embeddings', $result['semantic_engine']);
        $this->assertTrue($result['has_answer']);
    }

    public function test_weak_vector_matches_are_not_returned(): void
    {
        config()->set('realitylog.ai.enabled', true);

        $this->createMemory([
            'title' => 'Fridge compressor repair',
            'body' => 'The appliance was repaired at home.',
            'embedding' => [1.0, 0.0, 0.0],
        ]);

        $this->mock(EmbeddingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('available')->once()->andReturnTrue();
            $mock->shouldReceive('embed')
                ->once()
                ->with(['device guarantee ending'])
                ->andReturn([[0.0, 0.0, 1.0]]);
        });

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'device guarantee ending']));

        $result = $this->recallResult($response);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['results']);
        $this->assertSame('local_embeddings', $result['semantic_engine']);
        $this->assertFalse($result['has_answer']);
    }

    public function test_keyword_recall_still_works_when_embeddings_are_unavailable(): void
    {
        config()->set('realitylog.ai.enabled', true);

        $target = $this->createMemory([
            'title' => 'Phone receipt',
            'body' => 'Bought the phone from Star Tech.',
        ]);

        $this->mock(EmbeddingService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('available')->once()->andReturnFalse();
            $mock->shouldReceive('embed')->never();
        });

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'phone receipt']));

        $result = $this->recallResult($response);

        $this->assertSame(1, $result['total']);
        $this->assertSame($target->id, $result['results'][0]['memory']->id);
        $this->assertSame('exact', $result['results'][0]['match_type']);
        $this->assertFalse($result['results'][0]['vector_match']);
        $this->assertEqualsWithDelta(0.0, $result['results'][0]['similarity'], 0.0001);
        $this->assertSame('keywords', $result['semantic_engine']);
    }

    /**
     * @return array<string, mixed>
     */
    private function recallResult($response): array
    {
        $response->assertOk();

        return $response->viewData('result');
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
            'embedding' => $attributes['embedding'] ?? null,
            'embedded_at' => isset($attributes['embedding']) ? now() : null,
        ]);
    }
}
