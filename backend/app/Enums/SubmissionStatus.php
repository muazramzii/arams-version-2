<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case RevisionRequested = 'REVISION_REQUESTED';
    case Withdrawn = 'WITHDRAWN';
    case Superseded = 'SUPERSEDED';
}
