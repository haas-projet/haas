<?php

namespace App\Enums\Projects;

// La publication et la modération sont distinctes de la phase du projet.
enum ProjectPublicationState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
