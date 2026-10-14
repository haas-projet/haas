<?php

declare(strict_types=1);

namespace App\Data\Capsules;

use App\Exceptions\Capsules\ArtifactUnavailable;

/** Owns the verified bytes, never a path to reopen when the response is sent. */
final class ArtifactSnapshot
{
    /** @var resource|null */
    private mixed $stream;

    /** @param resource $stream */
    public function __construct(
        mixed $stream,
        public readonly int $size,
        public readonly string $sha256,
        public readonly string $downloadName,
    ) {
        $this->stream = $stream;
    }

    public function send(): void
    {
        try {
            if (! is_resource($this->stream) || ! rewind($this->stream)) {
                throw new ArtifactUnavailable;
            }
            while (! feof($this->stream)) {
                $bytes = fread($this->stream, 65536);
                if ($bytes === false) {
                    throw new ArtifactUnavailable;
                }
                echo $bytes;
            }
        } finally {
            $this->close();
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
