<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'employee_id', 'date',
        'sale_type', 'status', 'subtotal', 'discount', 'tax',
        'total', 'paid', 'remaining', 'interest_rate',
        'installment_months', 'monthly_installment', 'notes'
    ];

    // علاقة العملاء
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // علاقة الموظف
    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // علاقة تفاصيل الفاتورة (الأهم)
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // علاقة المدفوعات
    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    // علاقة خطة التقسيط
    public function installmentPlan()
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    // حساب المتبقي
    public function getRemainingAttribute()
    {
        return $this->total - $this->paid;
    }

    // هل الفاتورة مدفوعة بالكامل؟
    public function isFullyPaid()
    {
        return $this->remaining <= 0;
    }
}