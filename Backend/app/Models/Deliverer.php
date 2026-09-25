<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deliverer extends Model
{
    protected $primaryKey = 'id_livreur';
    protected $guarded = [];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'im_vehicule', 'im_vehicule');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'code_zone', 'code_zone');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'id_livreur');
    }
}