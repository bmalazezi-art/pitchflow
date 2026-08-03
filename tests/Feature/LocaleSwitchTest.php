<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_switching_is_not_blocked_by_public_rate_limit(): void
    {
        foreach (range(1, 30) as $attempt) {
            $this->post('/locale', ['locale' => $attempt % 2 === 0 ? 'sq' : 'en'])
                ->assertRedirect();
        }

        $this->assertSame('sq', session('locale'));
    }
}
