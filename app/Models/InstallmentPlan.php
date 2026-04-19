<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    protected $fillable = [
        'sale_id',
        'total_amount',
        'down_payment',
        'remaining_amount',
        'interest_rate',
        'number_of_months',
        'monthly_amount',
        'start_date',
        'end_date',
        'status'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function installmentPayments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }
}