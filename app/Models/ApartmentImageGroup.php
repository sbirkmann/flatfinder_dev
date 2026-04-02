<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ApartmentImageGroup extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['apartment_id', 'name', 'sort_order', 'is_active'];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }
}
