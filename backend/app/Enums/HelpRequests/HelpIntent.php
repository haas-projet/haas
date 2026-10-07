<?php

namespace App\Enums\HelpRequests;

// Contrat partagé B14/BV201/BC07 : ne pas créer un second enum dans l'atelier.
enum HelpIntent: string
{
    case Unblock = 'unblock';
    case ReviewSolution = 'review_solution';
    case ReproduceBehavior = 'reproduce_behavior';
    case AskQuestion = 'ask_question';
}
