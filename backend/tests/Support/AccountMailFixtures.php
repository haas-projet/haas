<?php

namespace Tests\Support;

use App\Models\User;
use App\Notifications\Identity\AccountMailNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

trait AccountMailFixtures
{
    private function latestAccountNotification(): AccountMailNotification
    {
        $payload = json_decode(DB::table('jobs')->where('queue', 'account-mail')->orderByDesc('id')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize(Crypt::decrypt($payload['data']['command']));
        $this->assertInstanceOf(SendQueuedNotifications::class, $job);
        $this->assertInstanceOf(AccountMailNotification::class, $job->notification);

        return $job->notification;
    }

    private function passwordToken(User $user): string
    {
        $notification = $this->latestAccountNotification();
        $url = $notification->toMail($user)->actionUrl;
        $fragment = parse_url($url, PHP_URL_FRAGMENT);
        $this->assertIsString($fragment);
        parse_str($fragment, $parameters);
        $this->assertIsString($parameters['token']);

        return $parameters['token'];
    }

    private function verificationPath(User $user): string
    {
        $url = $this->latestAccountNotification()->toMail($user)->actionUrl;

        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    private function loginMember(User $user): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/login', ['email' => $user->email, 'password' => 'mot-de-passe-de-test'])->assertOk();
    }

    /** @return array<string, string> */
    private function resetBody(User $user, string $token, string $password = 'nouveau-mot-de-passe-fictif'): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
    }
}
