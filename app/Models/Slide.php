<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Slide extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['slider_id', 'type', 'title', 'infoframe_id', 'iframe_url', 'sort'];

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }

    public function infoframe(): BelongsTo
    {
        return $this->belongsTo(Infoframe::class);
    }
}
