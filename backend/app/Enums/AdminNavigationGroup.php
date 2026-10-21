<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Admin sidebar groups, in display order. Labels are translated at render time.
 */
enum AdminNavigationGroup implements HasLabel
{
    case Competition;
    case Finance;
    case Community;
    case Store;
    case Marketplace;
    case Catalog;
    case Platform;

    public function getLabel(): string
    {
        return __($this->name);
    }
}
