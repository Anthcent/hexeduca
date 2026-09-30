<?php

namespace Modules\TeachingAssignments\Domain\ValueObjects;

enum TeachingRole: string
{
    case Titular = 'titular';
    case Substitute = 'suplente';
}
