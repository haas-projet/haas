<?php

namespace App\Enums\HelpOffers;

enum HelpContributionCategory: string
{
    case UsabilityFeedback = 'usability_feedback';
    case CodeReview = 'code_review';
    case ReproduceBehavior = 'reproduce_behavior';
    case ExplainConcept = 'explain_concept';
    case Documentation = 'documentation';
}
