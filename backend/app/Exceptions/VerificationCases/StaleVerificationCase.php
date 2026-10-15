<?php

declare(strict_types=1);

namespace App\Exceptions\VerificationCases;

use RuntimeException;

// 409 : lock_version périmé, état incompatible avec la transition demandée, ou
// cas intégré/retiré qui ne peut plus bouger. Message public générique.
final class StaleVerificationCase extends RuntimeException {}
