<?php

namespace App\Notifications\Identity;

use App\Enums\Identity\AccountStatus;
use App\Models\User;
use App\Support\Identity\AccountMailConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class AccountMailNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public readonly int $expiresAt;

    protected readonly string $emailHash;

    public function __construct(string $email, int $minutes)
    {
        app(AccountMailConfiguration::class)->validate();
        $this->emailHash = sha1($email);
        $this->expiresAt = now()->addMinutes($minutes)->getTimestamp();
        // L'insertion du job participe à la transaction ; le worker SQL ne le voit qu'après commit.
        $this->onConnection('account-mail')->onQueue('account-mail')->beforeCommit();
    }

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    protected function recipientIsCurrent(User $user): bool
    {
        return $user->status === AccountStatus::Active && now()->getTimestamp() < $this->expiresAt
            && hash_equals($this->emailHash, sha1($user->getEmailForVerification()));
    }

    abstract public function shouldSend(User $notifiable, string $channel): bool;

    abstract public function toMail(User $notifiable): MailMessage;
}
