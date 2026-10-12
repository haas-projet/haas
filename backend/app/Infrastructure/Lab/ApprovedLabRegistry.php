<?php

declare(strict_types=1);

namespace App\Infrastructure\Lab;

use App\Models\Lab\LabDefinition;

/** A first-party source manifest, never a user-selected PHP class or executable path. */
final class ApprovedLabRegistry
{
    /** @var array<string, string> */
    private const MODULE = [
        'app/Data/Lab/TestEventData.php' => 'f22303d93ca4c2bf16d57f4e8feb68c092513fa9594a0c2b77287bdf24474b19',
        'app/Data/Lab/ProcessedTestEvent.php' => '6eafdc710a59bbcdfe0034c4b49066ef76cb2da4083c6e254c0b0529ee784c0d',
        'app/Models/Lab/TestEvent.php' => '047c94e39158a3ef759105c38131a03573676eb38078a6178133353ebd893bdb',
        'app/Models/Lab/TestOrder.php' => 'f327f226cb7015dec3866919fdf8b8e7aa7e3e94b7cf6136cf19abbe03d9a4bb',
        'app/Models/Lab/LabConnection.php' => 'd3180385525502bb7b571ebd4921f94d045ebc0eeef92c6807867034745ab407',
        'app/Services/Lab/ProcessTestEventService.php' => 'd1588aa8d4d603e8d244cf2ed640bc50e5f8fb3302228105bd081ab5267f3de2',
        'database/migrations/2026_10_05_120000_create_test_events_table.php' => 'e0e039712daa6a4591173e06deeabab1b8400a0952d060dc2e2a074b513ae466',
        'database/migrations/2026_10_05_120100_create_test_orders_table.php' => '1df42479628605d08c0ab2923de3330623a4f513bd514cd0710c9b88a6db3121',
    ];

    /** @var array<string, string> */
    private const SUITE = [
        'tests/Integration/Lab/ProcessTestEventTest.php' => 'a45580aab4347e8df8ce766c3ffdc97cf30527bbfc5171d31be01ed7c0baf2a9',
        'tests/Integration/Lab/TestEventSchemaTest.php' => '55906f8d72d29f8fc23f85b3c623594563adced647e498bae854c01c3b80d17f',
        'tests/Integration/Lab/ConcurrentTestEventTest.php' => 'b16f53d582d524936b6658fc2a7764c0c4b967fcba380741e19080360d43cf65',
        'tests/Fixtures/process-test-event-concurrently.php' => 'efde1d3f73d7557e7bacb92fc4ecdb945ea07686bfa935c31a614db9fa73be69',
        'database/factories/Lab/TestEventFactory.php' => '32c775262f24029206d71b6b6f6519b4c98c4342c8fea935115f311324fefc24',
        'database/factories/Lab/TestOrderFactory.php' => '813f29ed8d0230bd0083e0eb18d982d9dcaaa4bbff47fbae516045eb9e6e05df',
    ];

    /** @return array{runner_key: string, release: string, module_version: string, source_commit: string, code_digest: string, suite_digest: string} */
    public function manifest(): array
    {
        return [
            'runner_key' => 'b1.idempotent.v1', 'release' => 'b1-source-20261011', 'module_version' => '1.0.0',
            'source_commit' => '524067b3eda56b0f6f04098423d707f6ebee4087',
            'code_digest' => $this->digest(self::MODULE), 'suite_digest' => $this->digest(self::SUITE),
        ];
    }

    public function matches(LabDefinition $definition): bool
    {
        foreach ($this->manifest() as $field => $expected) {
            if ($definition->getAttribute($field) !== $expected) {
                return false;
            }
        }

        return $this->integrity();
    }

    public function integrity(): bool
    {
        // Fixed paths relative to this installed source tree; no env/path/URL argument.
        $backend = dirname(__DIR__, 3);
        foreach ([...self::MODULE, ...self::SUITE] as $path => $expected) {
            $file = $backend.DIRECTORY_SEPARATOR.$path;
            if (! is_file($file) || is_link($file) || ! is_readable($file)
                || hash_file('sha256', $file) !== $expected) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $files */
    private function digest(array $files): string
    {
        ksort($files);
        $fingerprint = '';
        foreach ($files as $path => $digest) {
            $fingerprint .= $path."\0".$digest."\n";
        }

        return hash('sha256', $fingerprint);
    }
}
