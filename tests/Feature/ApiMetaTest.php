<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiMetaTest extends TestCase
{
    public function test_meta_is_public_and_returns_english_labels_by_default(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonCount(16, 'types')
            ->assertJsonCount(5, 'sources')
            ->assertJsonCount(6, 'reminder_types')
            ->assertJsonPath('types.0.value', 'purchase')
            ->assertJsonPath('types.0.label', 'Purchase')
            ->assertJsonPath('sources.3.value', 'voice')
            ->assertJsonPath('sources.3.label', 'Voice')
            ->assertJsonPath('reminder_types.0.label', 'Reminder')
            ->assertJsonPath('date_formats', ['d M Y', 'j F Y', 'd/m/Y', 'Y-m-d']);
    }

    public function test_meta_honours_the_bengali_locale_header(): void
    {
        $this->withHeader('X-Locale', 'bn')
            ->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonPath('locale', 'bn')
            ->assertJsonPath('types.0.label', 'কেনাকাটা')
            ->assertJsonPath('sources.3.label', 'কণ্ঠ')
            ->assertJsonPath('reminder_types.0.label', 'রিমাইন্ডার')
            ->assertJsonPath('languages.1.label', 'বাংলা');
    }

    public function test_meta_accepts_a_query_locale_and_falls_back_for_unknown_values(): void
    {
        $this->getJson('/api/v1/meta?locale=bn')
            ->assertOk()
            ->assertJsonPath('locale', 'bn')
            ->assertJsonPath('types.1.label', 'রিসিট');

        $this->getJson('/api/v1/meta?locale=fr')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('types.1.label', 'Receipt');
    }
}
