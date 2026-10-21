<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** An area the store delivers to, with its fee. Delivery is unavailable while none is active. */
#[Fillable(['name', 'fee_minor', 'estimate', 'is_active'])]
class DeliveryZone extends Model
{
    protected $attributes = ['is_active' => true, 'is_sample_data' => false];

    protected function casts(): array
    {
        return ['fee_minor' => 'integer', 'is_active' => 'boolean', 'is_sample_data' => 'boolean'];
    }
}
