<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'opening_hours', 'is_active'])]
class PickupLocation extends Model
{
    protected $attributes = ['is_active' => true, 'is_sample_data' => false];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_sample_data' => 'boolean'];
    }
}
