<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case RevisionRequested = 'REVISION_REQUESTED';
}
