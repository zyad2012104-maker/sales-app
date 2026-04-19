<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // جلب جميع الأصناف
    public function index()
    {
        $products = Product::all();
        return response()->json([
            'status' => true,
            'products' => $products
        ]);
    }

    // إضافة صنف جديد
    public function store(Request $request)
    {
        $product = Product::create($request->all());
        return response()->json([
            'status' => true,
            'product' => $product
        ]);
    }

    // جلب صنف معين
    public function show($id)
    {
        $product = Product::find($id);
        return response()->json([
            'status' => true,
            'product' => $product
        ]);
    }

    // تحديث صنف
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        $product->update($request->all());
        return response()->json([
            'status' => true,
            'product' => $product
        ]);
    }

    // حذف صنف
    public function destroy($id)
    {
        $product = Product::find($id);
        $product->delete();
        return response()->json([
            'status' => true,
            'message' => 'تم الحذف'
        ]);
    }
}