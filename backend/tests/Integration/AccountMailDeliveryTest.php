<?php

namespace Tests\Integration;

use App\Data\Identity\RegisterMemberData;
use App\Services\Identity\RegisterMemberService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\PostgresTestCase;
use Tests\Support\SpaHttpRequests;

final class AccountMailDeliveryTest extends PostgresTestCase
{
    use DatabaseMigrations, SpaHttpRequests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureSpa('database');
    }

    public function test_worker_only_sees_committed_registration_and_renders_a_real_local_mail(): void
    {
        config(['database.connections.mail_observer' => config('database.connections.pgsql')]);
        DB::beginTransaction();
        try {
            $user = app(RegisterMemberService::class)->register(new RegisterMemberData('MailFixture', 'mail@example.test', 'mot-de-passe-de-test', 'fixture-v1'));
            $this->assertDatabaseCount('jobs', 1);
            $this->assertSame(0, DB::connection('mail_observer')->table('jobs')->count());
            $this->assertSame(0, DB::connection('mail_observer')->table('users')->count());
            DB::commit();
            $this->assertSame(1, DB::connection('mail_observer')->table('jobs')->count());
            $job = Queue::connection('account-mail')->pop('account-mail');
            $this->assertNotNull($job);
            $job->fire();
            $job->delete();
            $transport = Mail::mailer('array')->getSymfonyTransport();
            $this->assertInstanceOf(ArrayTransport::class, $transport);
            $this->assertCount(1, $transport->messages());
            $mail = $transport->messages()->sole()->getOriginalMessage();
            $this->assertInstanceOf(Email::class, $mail);
            $this->assertSame('Vérifiez votre courriel HAAS', $mail->getSubject());
            $body = $mail->getHtmlBody();
            $this->assertIsString($body);
            $this->assertStringContainsString('https://api.haas.example.com/email/verify/'.$user->id, $body);
            $this->assertDatabaseCount('jobs', 0);
            $this->assertFalse($user->refresh()->hasVerifiedEmail());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('mail_observer');
        }
    }

    public function test_rolled_back_registration_cannot_leave_an_email_job(): void
    {
        try {
            DB::transaction(function (): void {
                app(RegisterMemberService::class)->register(new RegisterMemberData('RollbackMail', 'rollback@example.test', 'mot-de-passe-de-test', 'fixture-v1'));
                throw new RuntimeException('Annulation de fixture');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Annulation de fixture', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('jobs', 0);
    }
}
