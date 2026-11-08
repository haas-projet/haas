<?php

namespace App\Enums\HelpOffers;

enum HelpOfferState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
}
