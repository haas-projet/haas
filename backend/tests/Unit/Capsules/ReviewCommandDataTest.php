<?php

declare(strict_types=1);

namespace Tests\Unit\Capsules;

use App\Data\Capsules\ReviewCommandData;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReviewCommandDataTest extends TestCase
{
    public function test_submission_can_omit_the_review_note(): void
    {
        $command = new ReviewCommandData(1);
        $this->assertSame(1, $command->lockVersion);
        $this->assertNull($command->note);
    }

    public function test_note_length_counts_unicode_characters(): void
    {
        $note = str_repeat('é', 2000);
        $this->assertSame($note, (new ReviewCommandData(2147483646, $note))->note);
    }

    /** @return iterable<string, array{int, ?string}> */
    public static function invalidCommands(): iterable
    {
        yield 'zero' => [0, null];
        yield 'overflow after increment' => [2147483647, null];
        yield 'short note' => [1, 'trop court'];
        yield 'blank note' => [1, str_repeat(' ', 20)];
        yield 'long Unicode note' => [1, str_repeat('é', 2001)];
    }

    #[DataProvider('invalidCommands')]
    public function test_invalid_command_is_rejected(int $lockVersion, ?string $note): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ReviewCommandData($lockVersion, $note);
    }
}
