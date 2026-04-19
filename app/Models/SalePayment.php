<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    protected $fillable = [
        'sale_id',
        'amount',
        'payment_method',
        'payment_date',
        'reference_number',
        'notes'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}