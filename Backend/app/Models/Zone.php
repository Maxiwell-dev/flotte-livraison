<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $primaryKey = 'code_zone';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    public function deliverers()
    {
        return $this->hasMany(Deliverer::class, 'code_zone', 'code_zone');
    }
}