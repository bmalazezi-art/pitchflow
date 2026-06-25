<?php

namespace App\Enums;

enum FieldStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Closed = 'closed';
}
