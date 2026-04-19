<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barcode',
        'description',
        'purchase_price',
        'selling_price',
        'quantity',
        'min_quantity',
        'image',
        'unit'
    ];
}