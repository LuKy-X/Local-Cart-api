<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipper extends Model
{
    protected $fillable = [
        'shipper_name',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
