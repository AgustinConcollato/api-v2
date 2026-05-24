<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Incomplete = 'incomplete';
    case Published  = 'published';
    case Archived   = 'archived';
}
