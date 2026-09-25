<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $primaryKey = 'im_vehicule';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function deliverer()
    {
        return $this->hasOne(Deliverer::class, 'im_vehicule', 'im_vehicule');
    }
}