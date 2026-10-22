<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecallTest extends TestCase
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

    public function test_recall_cites_the_source_memory_and_answers_in_the_memory_language(): void
    {
        $receipt = $this->createMemory([
            'title' => 'নতুন ফোনের রিসিট',
            'body' => 'স্টার টেক থেকে ফোন কিনেছিলাম। দাম ৩২,৫০০ টাকা।',
            'detected_language' => 'bn',
            'memory_type' => 'receipt',
            'ai_metadata' => ['amount' => 'BDT 32,500', 'merchant' => 'Star Tech'],
        ]);

        $this->user->settings()->first()->update(['ai_locale' => 'bn']);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'ফোনের রিসিট কোন দোকান থেকে কিনেছিলাম']));

        $response->assertOk();
        $response->assertSee('নতুন ফোনের রিসিট');

        $result = $this->recallResult($response);

        $this->assertTrue($result['has_answer']);
        $this->assertSame('bn', $result['locale']);
        $this->assertNotNull($result['answer']);
        $this->assertStringContainsString('Star Tech', $result['answer']);
        $this->assertSame($receipt->id, $result['sources'][0]['memory']->id);
        $this->assertNotEmpty($result['sources'][0]['excerpt']);
    }

    public function test_english_question_answers_in_english_when_ai_locale_is_english(): void
    {
        $receipt = $this->createMemory([
            'title' => 'Phone receipt from Star Tech',
            'body' => 'Bought a new phone from Star Tech for BDT 32,500.',
            'memory_type' => 'receipt',
            'ai_metadata' => ['amount' => 'BDT 32,500', 'merchant' => 'Star Tech'],
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'Where did I buy the phone receipt?']));

        $result = $this->recallResult($response);

        $this->assertTrue($result['has_answer']);
        $this->assertSame('en', $result['locale']);
        $this->assertStringContainsString('Star Tech', $result['answer']);
        $this->assertSame($receipt->id, $result['sources'][0]['memory']->id);
    }

    public function test_unrelated_questions_return_no_fabricated_answer(): void
    {
        $this->createMemory(['title' => 'Phone receipt from Star Tech']);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'quantum entanglement experiments']));

        $result = $this->recallResult($response);

        $this->assertFalse($result['has_answer']);
        $this->assertNull($result['answer']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['sources']);
    }

    public function test_archived_memories_are_excluded_unless_requested(): void
    {
        $this->createMemory([
            'title' => 'Old phone receipt',
            'is_archived' => true,
        ]);

        $this->actingAs($this->user);

        $hidden = $this->recallResult($this->get(route('search.index', ['q' => 'phone receipt'])));
        $this->assertSame(0, $hidden['total']);

        $visible = $this->recallResult($this->get(route('search.index', ['q' => 'phone receipt', 'archived' => 1])));
        $this->assertSame(1, $visible['total']);
    }

    public function test_purchase_list_question_returns_multiple_cited_records(): void
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

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'my last three purchases']));

        $result = $this->recallResult($response);

        $this->assertTrue($result['has_answer']);
        $this->assertSame('list', $result['answer_kind']);
        $this->assertCount(3, $result['sources']);
        $this->assertStringContainsString('Laptop purchase', $result['answer']);
        $this->assertStringContainsString('Phone purchase', $result['answer']);
        $this->assertStringContainsString('Desk purchase', $result['answer']);
    }

    public function test_date_range_question_filters_by_event_date(): void
    {
        $this->createMemory([
            'title' => 'Dentist appointment',
            'body' => 'Dentist appointment in Dhanmondi.',
            'event_date' => now()->subYear()->toDateString(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('search.index', ['q' => 'dentist appointment last year']));

        $result = $this->recallResult($response);

        $this->assertSame('last_year', $result['date_range']['label']);
        $this->assertSame(1, $result['total']);
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
            'ai_metadata' => $attributes['ai_metadata'] ?? null,
            'is_archived' => $attributes['is_archived'] ?? false,
        ]);
    }
}
