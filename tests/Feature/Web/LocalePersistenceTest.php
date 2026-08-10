<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class LocalePersistenceTest extends TestCase
{
    public function test_language_query_writes_locale_cookie(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertCookie('gb_locale', 'en', false);
    }

    public function test_locale_cookie_keeps_english_after_page_navigation(): void
    {
        $this->withUnencryptedCookie('gb_locale', 'en')
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee(i18n('auth.loginTitle', locale: 'en'));
    }

    public function test_language_query_overrides_existing_locale_cookie(): void
    {
        $this->withUnencryptedCookie('gb_locale', 'zh')
            ->get('/login?lang=en')
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertCookie('gb_locale', 'en', false)
            ->assertSee(i18n('auth.loginTitle', locale: 'en'));
    }
    public function test_english_pages_include_localized_native_validation_messages(): void
    {
        $this->withUnencryptedCookie('gb_locale', 'en')
            ->get('/login')
            ->assertOk()
            ->assertSee('Please select an option.')
            ->assertSee('Please enter a valid email address.')
            ->assertSee('gbLocalizedValidation');
    }


}
