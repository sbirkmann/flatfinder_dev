<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['apartment_id', 'name', 'sqm'];

    public function apartment()
    {
        return $this->belongsTo(Apartment::class);
    }
}
