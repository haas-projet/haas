<?php

namespace App\Data\Identity;

use InvalidArgumentException;

final readonly class UpdateProfileData
{
    /**
     * @param  array{bio?: string, country?: ?string, primary_language?: string, github_url?: ?string}  $attributes
     * @param  ?list<string>  $technologyIds
     */
    public function __construct(public int $lockVersion, public array $attributes, public ?array $technologyIds)
    {
        if ($lockVersion < 0 || $lockVersion > 2147483646
            || array_diff(array_keys($attributes), ['bio', 'country', 'primary_language', 'github_url']) !== []
            || ($attributes === [] && $technologyIds === null)
            || ($technologyIds !== null && (count($technologyIds) > 8 || count(array_unique(array_map('strtolower', $technologyIds))) !== count($technologyIds)))) {
            throw new InvalidArgumentException('Modification de profil hors contrat.');
        }
    }
}
