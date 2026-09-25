<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $primaryKey = 'id_course';
    protected $guarded = [];

    public function deliverer()
    {
        return $this->belongsTo(Deliverer::class, 'id_livreur');
    }

    public function histories()
    {
        return $this->hasMany(StatusHistory::class, 'id_course');
    }
}