<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class VirtualTour extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = ['project_id', 'name'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    
    public function points()
    {
        return $this->hasMany(VirtualTourPoint::class)->orderBy('sort_index');
    }
}
