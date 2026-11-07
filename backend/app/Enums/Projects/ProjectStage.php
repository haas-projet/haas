<?php

namespace App\Enums\Projects;

// Phase déclarée par le propriétaire, sans certification de qualité.
enum ProjectStage: string
{
    case Idea = 'idea';
    case Prototype = 'prototype';
    case InDevelopment = 'in_development';
    case Launched = 'launched';
}
