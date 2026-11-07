<?php

declare(strict_types=1);

namespace Tests\Unit\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use App\Support\Capsules\VerifiedArtifactFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class VerifiedArtifactFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Storage::fake ignores a supplied root and cleans its shared default
        // directory; use an independent real disk for filesystem race probes.
        Storage::set('local', Storage::build([
            'driver' => 'local',
            'root' => storage_path('framework/testing/b27-files/'.Str::uuid()),
            'throw' => true,
        ]));
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeKeys(): iterable
    {
        foreach (['../fixture.txt', 'kits/../fixture.zip', 'kits//fixture.zip', 'kits/./fixture.zip',
            'kits\\fixture.zip', 'kits/%2e%2e/fixture.zip', '/kits/fixture.zip', 'C:/fixture.zip',
            '//host/fixture.zip', 'https://example.invalid/fixture.zip', 'file:///fixture.zip',
            'kits/fixture.zip?x=1', 'kits/fixture.zip#anchor', "kits/fixture\0.zip", 'other/fixture.zip',
            'kits/'.str_repeat('a', 129).'.zip', 'kits/a/b/c/d/e/f/g/h/fixture.zip'] as $key) {
            yield bin2hex($key) => [$key];
        }
    }

    #[DataProvider('unsafeKeys')]
    public function test_unsafe_server_keys_are_rejected(string $key): void
    {
        Storage::disk('local')->put('artifacts/kits/fixture.zip', 'fixture');
        $this->expectException(ArtifactUnavailable::class);
        (new VerifiedArtifactFiles)->kit($this->artifact($key), 'fixture.zip');
    }

    public function test_wrong_size_is_rejected(): void
    {
        Storage::disk('local')->put('artifacts/kits/fixture.zip', 'fixture');
        $artifact = $this->artifact('kits/fixture.zip')->forceFill(['size' => 8]);
        $this->expectException(ArtifactUnavailable::class);
        (new VerifiedArtifactFiles)->kit($artifact, 'fixture.zip');
    }

    public function test_actual_bytes_above_declared_size_are_rejected(): void
    {
        Storage::disk('local')->put('artifacts/kits/fixture.zip', 'fixture-extra');
        $this->expectException(ArtifactUnavailable::class);
        (new VerifiedArtifactFiles)->kit($this->artifact('kits/fixture.zip'), 'fixture.zip');
    }

    public function test_snapshot_survives_deletion_without_reopening_the_path(): void
    {
        Storage::disk('local')->put('artifacts/kits/fixture.zip', 'fixture');
        $snapshot = (new VerifiedArtifactFiles)->kit($this->artifact('kits/fixture.zip'), 'fixture.zip');
        Storage::disk('local')->delete('artifacts/kits/fixture.zip');
        ob_start();
        $snapshot->send();
        $this->assertSame('fixture', ob_get_clean());
        $snapshot->close();
        $this->expectException(ArtifactUnavailable::class);
        $snapshot->send();
    }

    public function test_linked_parent_is_rejected_with_real_filesystem_link(): void
    {
        Storage::disk('local')->put('outside-artifacts/fixture.zip', 'fixture');
        Storage::disk('local')->makeDirectory('artifacts/kits');
        $link = Storage::disk('local')->path('artifacts/kits/linked');
        $target = Storage::disk('local')->path('outside-artifacts');
        $this->createLink($link, $target);
        try {
            $this->expectException(ArtifactUnavailable::class);
            (new VerifiedArtifactFiles)->kit($this->artifact('kits/linked/fixture.zip'), 'fixture.zip');
        } finally {
            $this->removeLink($link);
        }
    }

    public function test_linked_root_is_rejected_with_real_filesystem_link(): void
    {
        Storage::disk('local')->put('outside-artifacts/kits/fixture.zip', 'fixture');
        $link = Storage::disk('local')->path('artifacts');
        $target = Storage::disk('local')->path('outside-artifacts');
        $this->createLink($link, $target);
        try {
            $this->expectException(ArtifactUnavailable::class);
            (new VerifiedArtifactFiles)->kit($this->artifact('kits/fixture.zip'), 'fixture.zip');
        } finally {
            $this->removeLink($link);
        }
    }

    private function artifact(string $path): Artifact
    {
        return (new Artifact)->forceFill(['private_path' => $path, 'size' => 7, 'sha256' => hash('sha256', 'fixture')]);
    }

    public function test_replaced_parent_is_refused_even_after_canonical_path_was_cached(): void
    {
        Storage::disk('local')->put('artifacts/kits/fixture.zip', 'fixture');
        (new VerifiedArtifactFiles)->kit($this->artifact('kits/fixture.zip'), 'fixture.zip')->close();
        Storage::disk('local')->put('outside-artifacts/fixture.zip', 'fixture');
        $link = Storage::disk('local')->path('artifacts/kits');
        $old = Storage::disk('local')->path('old-kits');
        $this->assertFixturePath($link);
        $this->assertFixturePath($old);
        $this->assertTrue(rename($link, $old));
        $this->createLink($link, Storage::disk('local')->path('outside-artifacts'));
        try {
            $this->expectException(ArtifactUnavailable::class);
            (new VerifiedArtifactFiles)->kit($this->artifact('kits/fixture.zip'), 'fixture.zip');
        } finally {
            $this->removeLink($link);
        }
    }

    public function test_final_link_on_posix_or_junction_parent_on_windows_is_refused(): void
    {
        Storage::disk('local')->put('outside-artifacts/fixture.zip', 'fixture');
        Storage::disk('local')->makeDirectory('artifacts/kits');
        $key = PHP_OS_FAMILY === 'Windows' ? 'kits/leaf/fixture.zip' : 'kits/fixture.zip';
        $link = Storage::disk('local')->path('artifacts/'.(PHP_OS_FAMILY === 'Windows' ? 'kits/leaf' : $key));
        $target = Storage::disk('local')->path(PHP_OS_FAMILY === 'Windows' ? 'outside-artifacts' : 'outside-artifacts/fixture.zip');
        $this->createLink($link, $target);
        try {
            $this->expectException(ArtifactUnavailable::class);
            (new VerifiedArtifactFiles)->kit($this->artifact($key), 'fixture.zip');
        } finally {
            $this->removeLink($link);
        }
    }

    private function createLink(string $link, string $target): void
    {
        $this->assertFixturePath($link);
        $this->assertFixturePath($target);
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command',
                'New-Item -ItemType Junction -Path $env:B27_LINK -Target $env:B27_TARGET | Out-Null'], base_path(),
                ['B27_LINK' => $link, 'B27_TARGET' => $target], null, 20);
            $this->assertSame(0, $process->run(), $process->getErrorOutput());
            clearstatcache(true, $link);
            $this->assertTrue(is_dir($link));
        } else {
            $this->assertTrue(symlink($target, $link));
            $this->assertTrue(is_link($link));
        }
    }

    private function removeLink(string $link): void
    {
        $this->assertFixturePath($link);
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-Command',
                '[System.IO.Directory]::Delete($env:B27_LINK, $false)'], base_path(), ['B27_LINK' => $link], null, 20);
            $this->assertSame(0, $process->run(), $process->getErrorOutput());
        } else {
            $this->assertTrue(unlink($link));
        }
    }

    private function assertFixturePath(string $path): void
    {
        $prefix = str_replace('\\', '/', storage_path('framework/testing')).'/';
        $this->assertStringStartsWith($prefix, str_replace('\\', '/', $path));
    }
}
