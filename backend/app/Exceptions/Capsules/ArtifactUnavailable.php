<?php

declare(strict_types=1);

namespace App\Exceptions\Capsules;

use RuntimeException;

/**
 * Téléchargement refusé pour toute cause autre qu'une Policy (artefact
 * inactif, chemin suspect, fichier manquant). Transformé en 404 par le
 * Controller : aucune divulgation de chemin disque ni de distinction
 * fine entre les causes (RM06, §13 retrait).
 */
final class ArtifactUnavailable extends RuntimeException {}
