<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_guest_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get(route('privacy'))->assertOk();
    }

    public function test_authenticated_pages_render(): void
    {
        $user = User::where('email', 'demo@realitylog.app')->firstOrFail();
        $memory = Memory::where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user);

        $routes = [
            route('dashboard'),
            route('capture.create'),
            route('memories.index'),
            route('memories.favorites'),
            route('memories.archived'),
            route('memories.show', $memory),
            route('memories.edit', $memory),
            route('timeline.index'),
            route('reminders.index'),
            route('settings.edit'),
            route('search.index'),
            route('search.index', ['q' => 'ফ্রিজ মেরামত']),
            route('search.index', ['q' => 'phone receipt']),
            route('export'),
            route('help'),
        ];

        foreach ($routes as $route) {
            $this->get($route)->assertOk();
        }
    }
}
