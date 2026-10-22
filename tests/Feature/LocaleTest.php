<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_switch_the_interface_language(): void
    {
        $this->post(route('locale.update', 'bn'))
            ->assertRedirect();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('সাইন ইন')
            ->assertSee('lang="bn"', false);
    }

    public function test_switching_language_persists_for_a_signed_in_user(): void
    {
        $user = User::factory()->create();
        UserSetting::create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('locale.update', 'bn'))
            ->assertRedirect();

        $this->assertSame('bn', $user->settings()->first()->interface_locale);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="bn"', false);
    }

    public function test_interface_language_and_answer_language_are_independent(): void
    {
        $user = User::factory()->create();
        UserSetting::create([
            'user_id' => $user->id,
            'interface_locale' => 'en',
            'ai_locale' => 'en',
        ]);

        Memory::create([
            'user_id' => $user->id,
            'title' => 'Phone receipt',
            'memory_type' => 'receipt',
            'source_type' => 'text',
            'body' => 'Bought a phone from Star Tech.',
            'detected_language' => 'en',
            'captured_at' => now(),
            'processing_status' => 'ready',
            'ai_metadata' => ['merchant' => 'Star Tech'],
        ]);

        $this->actingAs($user)
            ->patch(route('settings.language'), [
                'interface_locale' => 'en',
                'ai_locale' => 'bn',
                'date_format' => 'd M Y',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('en', $user->settings()->first()->interface_locale);
        $this->assertSame('bn', $user->settings()->first()->ai_locale);

        $response = $this->get(route('search.index', ['q' => 'phone receipt where bought']));

        $response->assertOk();

        $result = $response->viewData('result');

        $this->assertSame('bn', $result['locale']);
        $this->assertNotNull($result['answer']);
    }

    public function test_invalid_locales_are_rejected(): void
    {
        $this->post('/locale/fr')->assertNotFound();
    }
}
