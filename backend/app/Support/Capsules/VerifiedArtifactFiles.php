<?php

declare(strict_types=1);

namespace App\Support\Capsules;

use App\Data\Capsules\ArtifactSnapshot;
use App\Exceptions\Capsules\ArtifactUnavailable;
use App\Models\Capsules\Artifact;
use Illuminate\Support\Facades\Storage;

/** Server registry only. No upload, extraction, URL, subprocess or user path. */
final class VerifiedArtifactFiles
{
    public const MAX_KIT_BYTES = 20 * 1024 * 1024;

    public const MAX_NOTICES_BYTES = 1024 * 1024;

    public function kit(Artifact $artifact, string $name): ArtifactSnapshot
    {
        return $this->snapshot((string) $artifact->private_path, 'kits', (int) $artifact->size,
            (string) $artifact->sha256, self::MAX_KIT_BYTES, $name);
    }

    public function notices(Artifact $artifact, string $name): ArtifactSnapshot
    {
        return $this->snapshot((string) $artifact->notices_path, 'notices', (int) $artifact->notices_size,
            (string) $artifact->notices_sha256, self::MAX_NOTICES_BYTES, $name);
    }

    private function snapshot(string $key, string $namespace, int $size, string $digest, int $maximum, string $name): ArtifactSnapshot
    {
        if ($size < 1 || $size > $maximum || preg_match('/^[0-9a-f]{64}$/D', $digest) !== 1
            || strlen($key) > 1024 || ! str_starts_with($key, $namespace.'/')
            || config('filesystems.disks.local.driver') !== 'local') {
            throw new ArtifactUnavailable;
        }
        $segments = explode('/', $key);
        if (count($segments) > 8) {
            throw new ArtifactUnavailable;
        }
        foreach ($segments as $segment) {
            if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/D', $segment) !== 1) {
                throw new ArtifactUnavailable;
            }
        }
        $root = rtrim(Storage::disk('local')->path('artifacts'), '/\\');
        clearstatcache(true, $root);
        $canonicalRoot = realpath($root);
        if ($canonicalRoot === false || is_link($root) || $this->normal($root) !== $this->normal($canonicalRoot)) {
            throw new ArtifactUnavailable;
        }
        $path = $root;
        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR.$segment;
            clearstatcache(true, $path);
            if (is_link($path) || ! file_exists($path)) {
                throw new ArtifactUnavailable;
            }
        }
        $real = realpath($path);
        $stat = @lstat($path);
        if ($real === false || $this->normal($real) !== $this->normal($path)
            || ! str_starts_with($this->normal($real), $this->normal($canonicalRoot).'/')
            || $stat === false || ($stat['mode'] & 0170000) !== 0100000) {
            throw new ArtifactUnavailable;
        }
        $source = @fopen($path, 'rb');
        if ($source === false) {
            throw new ArtifactUnavailable;
        }
        $copy = null;
        try {
            $opened = fstat($source);
            clearstatcache(true, $path);
            if ($opened === false || ($opened['mode'] & 0170000) !== 0100000 || $opened['size'] !== $size
                || $opened['dev'] !== $stat['dev'] || $opened['ino'] !== $stat['ino']
                || is_link($path) || realpath($path) !== $real) {
                throw new ArtifactUnavailable;
            }
            $copy = @fopen('php://temp/maxmemory:1048576', 'w+b');
            if ($copy === false) {
                throw new ArtifactUnavailable;
            }
            $hash = hash_init('sha256');
            $count = 0;
            while (! feof($source)) {
                $bytes = fread($source, max(1, min(65536, $maximum - $count + 1)));
                if ($bytes === false || ($bytes === '' && ! feof($source))) {
                    throw new ArtifactUnavailable;
                }
                $count += strlen($bytes);
                if ($count > $size || $count > $maximum || fwrite($copy, $bytes) !== strlen($bytes)) {
                    throw new ArtifactUnavailable;
                }
                hash_update($hash, $bytes);
            }
            if ($count !== $size || ! hash_equals($digest, hash_final($hash))) {
                throw new ArtifactUnavailable;
            }
            rewind($copy);
            $snapshot = new ArtifactSnapshot($copy, $count, $digest, $name);
            $copy = null;

            return $snapshot;
        } finally {
            fclose($source);
            if (is_resource($copy)) {
                fclose($copy);
            }
        }
    }

    private function normal(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
