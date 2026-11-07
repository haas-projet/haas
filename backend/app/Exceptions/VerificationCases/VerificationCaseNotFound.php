<?php

declare(strict_types=1);

namespace App\Exceptions\VerificationCases;

use RuntimeException;

// Parent masqué/retiré/non publié ou cas inaccessible : traité comme 404 pour
// ne jamais révéler l'existence de la ressource à un acteur sans droit.
final class VerificationCaseNotFound extends RuntimeException {}
