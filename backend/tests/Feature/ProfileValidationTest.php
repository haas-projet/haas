<?php

namespace Tests\Feature;

use App\Http\Requests\Identity\UpdateProfileRequest;
use App\Models\User;
use App\Support\Identity\ProfileInitials;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ProfileValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = (new User)->setDateFormat('Y-m-d H:i:s')->forceFill([
            'id' => 'b0fbc5aa-662e-4cac-83eb-a21259c98e03', 'email_verified_at' => '2026-10-02 12:00:00',
        ]);
        $this->actingAs($user, 'web');
        Route::patch('/api/v1/profile-validation-fixture', fn (UpdateProfileRequest $request) => [
            'attributes' => $request->profileData()->attributes, 'technologies' => $request->profileData()->technologyIds,
        ]);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_cannot_reach_the_profile_service(array $payload, string $field): void
    {
        $this->patchJson('/api/v1/profile-validation-fixture', $payload)->assertUnprocessable()->assertJsonValidationErrors($field, 'error.fields');
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'version required' => [['bio' => 'texte'], 'lock_version'];
        yield 'integer strict' => [['lock_version' => '0', 'bio' => 'texte'], 'lock_version'];
        yield 'negative version' => [['lock_version' => -1, 'bio' => 'texte'], 'lock_version'];
        yield 'overflow' => [['lock_version' => 2147483647, 'bio' => 'texte'], 'lock_version'];
        yield 'empty patch' => [['lock_version' => 0], 'profile'];
        yield 'bio 501' => [['lock_version' => 0, 'bio' => str_repeat('é', 501)], 'bio'];
        yield 'bio control' => [['lock_version' => 0, 'bio' => "texte\x00suite"], 'bio'];
        yield 'country 101' => [['lock_version' => 0, 'country' => str_repeat('é', 101)], 'country'];
        yield 'language empty' => [['lock_version' => 0, 'primary_language' => ''], 'primary_language'];
        yield 'language invalid' => [['lock_version' => 0, 'primary_language' => '<script>'], 'primary_language'];
        yield 'technologies null' => [['lock_version' => 0, 'technology_ids' => null], 'technology_ids'];
        yield 'technologies object' => [['lock_version' => 0, 'technology_ids' => ['one' => 'b0fbc5aa-662e-4cac-83eb-a21259c98e03']], 'technology_ids'];
        yield 'nine technologies' => [['lock_version' => 0, 'technology_ids' => array_fill(0, 9, 'b0fbc5aa-662e-4cac-83eb-a21259c98e03')], 'technology_ids'];
        yield 'invalid UUID' => [['lock_version' => 0, 'technology_ids' => ['not-a-uuid']], 'technology_ids.0'];
        yield 'duplicate UUID case' => [['lock_version' => 0, 'technology_ids' => ['b0fbc5aa-662e-4cac-83eb-a21259c98e03', 'B0FBC5AA-662E-4CAC-83EB-A21259C98E03']], 'technology_ids.0'];
        foreach (['http://github.com/awa', 'https://github.com.evil.test/awa', 'https://github.com@evil.test/awa', 'https://user@github.com/awa', 'https://github.com:443/awa', 'javascript:alert(1)', 'https://github.com/awa?redirect=evil', 'https://github.com/awa#fragment', 'https://github.com\\@evil.test/awa', 'https://github.com/'.str_repeat('a', 2048)] as $index => $url) {
            yield 'URL '.$index => [['lock_version' => 0, 'github_url' => $url], 'github_url'];
        }
        foreach (['role', 'user_id', 'author_id', 'email', 'handle', 'is_demo', 'contributions', 'avatar_url', 'directory_visible'] as $field) {
            yield 'protected '.$field => [['lock_version' => 0, 'bio' => 'texte', $field => null], $field];
        }
    }

    public function test_valid_partial_patch_preserves_absence_and_accepts_unicode_boundaries(): void
    {
        $this->patchJson('/api/v1/profile-validation-fixture', ['lock_version' => 0, 'bio' => str_repeat('é', 500), 'primary_language' => 'pt-BR'])
            ->assertOk()->assertExactJson(['attributes' => ['bio' => str_repeat('é', 500), 'primary_language' => 'pt-BR'], 'technologies' => null]);
        $this->patchJson('/api/v1/profile-validation-fixture', ['lock_version' => 0, 'bio' => null, 'country' => null, 'github_url' => null, 'technology_ids' => []])
            ->assertOk()->assertExactJson(['attributes' => ['bio' => '', 'country' => null, 'github_url' => null], 'technologies' => []]);
        $this->patchJson('/api/v1/profile-validation-fixture', ['lock_version' => 0, 'github_url' => 'https://github.com/haas-projet/haas'])
            ->assertOk();
    }

    public function test_avatar_initials_are_unicode_text_with_no_external_image(): void
    {
        $this->assertSame('ÉD', ProfileInitials::fromHandle('Élodie_Diop'));
        $this->assertSame('李', ProfileInitials::fromHandle('李明'));
        $this->assertSame('?', ProfileInitials::fromHandle('🌍🌍🌍'));
    }
}
