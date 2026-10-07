<?php

namespace App\Enums\HelpRequests;

enum SubmissionMode: string
{
    case Draft = 'draft';
    case Publish = 'publish';
}
