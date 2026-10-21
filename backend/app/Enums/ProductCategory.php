<?php

namespace App\Enums;

enum ProductCategory: string
{
    use EnumHelpers;

    case Jerseys = 'jerseys';
    case Apparel = 'apparel';
    case Accessories = 'accessories';
    case Equipment = 'equipment';
}
