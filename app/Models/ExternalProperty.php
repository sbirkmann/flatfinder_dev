<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalProperty extends Model
{
    protected $fillable = [
        'integration_id', 'external_id', 'name', 'rooms', 'bathrooms', 'sqm',
        'marketing_type', 'status', 'price', 'warm_rent', 'available_from',
        'outdoor_area', 'additional_costs', 'description', 'raw_data'
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
