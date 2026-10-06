<?php

namespace App\Exceptions\Idempotency;

use RuntimeException;

final class IdempotencyStorageFailed extends RuntimeException {}
