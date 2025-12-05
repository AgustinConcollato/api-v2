<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Incomplete = 'incomplete';
    case PendingPrices = 'pending_prices';
    case PendingBarcode = 'pending_barcode';
    case Published = 'published';
    case Archived = 'archived';
    
}
