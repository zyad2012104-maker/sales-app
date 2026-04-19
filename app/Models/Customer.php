<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'id_number', 'total_credit'
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}