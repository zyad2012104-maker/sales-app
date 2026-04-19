<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPayment extends Model
{
    protected $fillable = [
        'installment_plan_id',
        'installment_number',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'late_fee'
    ];

    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }
}