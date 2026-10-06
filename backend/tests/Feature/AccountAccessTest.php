<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SpaHttpRequests;
use Tests\TestCase;

final class AccountAccessTest extends TestCase
{
    use SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa();
    }

    public function test_information_is_public_without_any_account_lookup_or_fake_contact(): void
    {
        config(['account.support_email' => null]);
        $this->browserRequest('GET', '/api/v1/account-access')->assertOk()
            ->assertExactJsonStructure(['data' => ['message', 'contact_email']])->assertJsonPath('data.contact_email', null)
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.haas.example.com');
        config(['account.support_email' => 'recours@example.test']);
        $this->browserRequest('GET', '/api/v1/account-access')->assertOk()->assertJsonPath('data.contact_email', 'recours@example.test');
        $this->browserRequest('GET', '/api/v1/account-access?email=private@example.test')->assertUnprocessable();
        $this->browserRequest('GET', '/api/v1/account-access', ['user_id' => 'another-member'])->assertUnprocessable();
    }

    #[DataProvider('invalidContacts')]
    public function test_invalid_contact_configuration_is_not_exposed(mixed $contact): void
    {
        config(['account.support_email' => $contact]);
        $this->browserRequest('GET', '/api/v1/account-access')->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE')
            ->assertJsonMissingPath('data')->assertHeader('Cache-Control', 'no-store, private');
    }

    /** @return iterable<array{mixed}> */
    public static function invalidContacts(): iterable
    {
        yield ['javascript:alert(1)'];
        yield ["recours@example.test\r\nBcc: private@example.test"];
        yield [['recours@example.test']];
        yield [true];
    }

    public function test_me_is_private_even_without_json_accept_header_or_with_a_bearer(): void
    {
        $this->browserRequest('GET', '/api/v1/me', headers: ['Accept' => 'text/html'])->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTHENTICATION_REQUIRED')->assertHeader('Cache-Control', 'no-store, private');
        $this->browserRequest('GET', '/api/v1/me', headers: ['Authorization' => 'Bearer fictitious-token'])->assertUnauthorized();
    }
}
