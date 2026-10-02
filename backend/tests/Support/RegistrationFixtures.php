<?php

namespace Tests\Support;

trait RegistrationFixtures
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32)), 'registration.terms_version' => 'fixture-v1']);
    }

    /** @return array<string, mixed> */
    private function registrationPayload(): array
    {
        return [
            'handle' => 'Awa', 'email' => 'awa@example.test',
            'password' => 'Mot-de-passe-fictif-2026', 'password_confirmation' => 'Mot-de-passe-fictif-2026',
            'terms_accepted' => true, 'terms_version' => 'fixture-v1',
        ];
    }
}
