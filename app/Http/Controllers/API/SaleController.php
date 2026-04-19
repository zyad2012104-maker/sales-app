<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPayment;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleController extends Controller
{
    // إنشاء فاتورة بيع جديدة
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'sale_type' => 'required|in:cash,credit,installment,visa,wallet',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'discount' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0', // المقدم (للتقسيط)
            'installment_months' => 'required_if:sale_type,installment|nullable|integer|min:1|max:36',
        ]);

        try {
            DB::beginTransaction();

            // حساب إجمالي الفاتورة
            $subtotal = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $itemTotal = $product->selling_price * $item['quantity'];
                $subtotal += $itemTotal;

                // التحقق من الكمية المتوفرة
                if ($product->quantity < $item['quantity']) {
                    return response()->json([
                        'message' => "الكمية المطلوبة للمنتج {$product->name} غير متوفرة",
                        'available' => $product->quantity
                    ], 400);
                }

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal
                ];
            }

            // حساب الإجمالي بعد الخصم
            $discount = $request->discount ?? 0;
            $total = $subtotal - $discount;

            // إنشاء رقم فاتورة فريد
            $invoiceNumber = 'INV-' . time() . '-' . rand(100, 999);

            // إنشاء الفاتورة
            $sale = Sale::create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $request->customer_id,
                'employee_id' => 1, // مؤقتاً - سيتم تغييره بعد إضافة المصادقة
                'date' => now(),
                'sale_type' => $request->sale_type,
                'status' => $request->sale_type == 'cash' ? 'completed' : 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => 0,
                'total' => $total,
                'paid' => $request->sale_type == 'cash' ? $total : ($request->down_payment ?? 0),
                'remaining' => $request->sale_type == 'cash' ? 0 : ($total - ($request->down_payment ?? 0)),
                'interest_rate' => $request->sale_type == 'installment' ? Setting::where('key', 'default_interest_rate')->first()->value ?? 10 : 0,
                'installment_months' => $request->installment_months ?? 0,
                'notes' => $request->notes
            ]);

            // إنشاء تفاصيل الفاتورة وتحديث المخزون
            foreach ($itemsData as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['product']->selling_price,
                    'total' => $item['total']
                ]);

                // خصم الكمية من المخزون
                $item['product']->decrement('quantity', $item['quantity']);
            }

            // إذا كان البيع تقسيط، قم بإنشاء خطة التقسيط
            if ($request->sale_type == 'installment' && $request->installment_months > 0) {
                $this->createInstallmentPlan($sale, $request);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الفاتورة بنجاح',
                'sale' => $sale->load('items.product', 'customer'),
                'invoice_number' => $invoiceNumber
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء الفاتورة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // إنشاء خطة تقسيط
    private function createInstallmentPlan($sale, $request)
    {
        $downPayment = $request->down_payment ?? 0;
        $remainingAfterDown = $sale->total - $downPayment;
        $interestRate = Setting::where('key', 'default_interest_rate')->first()->value ?? 10;
        
        // حساب المبلغ مع الفائدة
        $interestAmount = $remainingAfterDown * ($interestRate / 100);
        $totalWithInterest = $remainingAfterDown + $interestAmount;
        $monthlyAmount = round($totalWithInterest / $request->installment_months, 2);

        // إنشاء خطة التقسيط
        $installmentPlan = InstallmentPlan::create([
            'sale_id' => $sale->id,
            'total_amount' => $totalWithInterest,
            'down_payment' => $downPayment,
            'remaining_amount' => $totalWithInterest,
            'interest_rate' => $interestRate,
            'number_of_months' => $request->installment_months,
            'monthly_amount' => $monthlyAmount,
            'start_date' => now(),
            'end_date' => now()->addMonths($request->installment_months),
            'status' => 'active'
        ]);

        // إنشاء الأقساط الشهرية
        for ($i = 1; $i <= $request->installment_months; $i++) {
            InstallmentPayment::create([
                'installment_plan_id' => $installmentPlan->id,
                'installment_number' => $i,
                'amount' => $monthlyAmount,
                'due_date' => now()->addMonths($i),
                'status' => 'pending'
            ]);
        }

        return $installmentPlan;
    }

    // جلب جميع الفواتير
    public function index()
    {
        $sales = Sale::with('items.product', 'customer', 'installmentPlan')->orderBy('id', 'desc')->get();
        return response()->json([
            'success' => true,
            'sales' => $sales
        ]);
    }

    // جلب فاتورة محددة
    public function show($id)
    {
        $sale = Sale::with('items.product', 'customer', 'installmentPlan.installmentPayments')->find($id);
        
        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'الفاتورة غير موجودة'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'sale' => $sale
        ]);
    }

    // إضافة دفعة لفاتورة آجلة أو تقسيط
    public function addPayment(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,visa,wallet,bank_transfer'
        ]);

        $sale = Sale::findOrFail($id);

        if ($sale->remaining <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'الفاتورة مدفوعة بالكامل'
            ], 400);
        }

        if ($request->amount > $sale->remaining) {
            return response()->json([
                'success' => false,
                'message' => 'المبلغ المدفوع أكبر من المتبقي',
                'remaining' => $sale->remaining
            ], 400);
        }

        try {
            DB::beginTransaction();

            // إضافة الدفعة
            $salePayment = \App\Models\SalePayment::create([
                'sale_id' => $sale->id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => now(),
                'notes' => $request->notes
            ]);

            // تحديث الفاتورة
            $newPaid = $sale->paid + $request->amount;
            $newRemaining = $sale->total - $newPaid;
            
            $sale->update([
                'paid' => $newPaid,
                'remaining' => $newRemaining,
                'status' => $newRemaining <= 0 ? 'completed' : 'partial'
            ]);

            // إذا كان التقسيط، تحديث القسط المدفوع
            if ($sale->sale_type == 'installment' && $sale->installmentPlan) {
                $this->updateInstallmentPayment($sale->installmentPlan, $request->amount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الدفعة بنجاح',
                'remaining' => $newRemaining,
                'status' => $sale->status
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الدفعة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // تحديث الأقساط عند الدفع
    private function updateInstallmentPayment($installmentPlan, $amount)
    {
        $nextPending = $installmentPlan->installmentPayments()
            ->where('status', 'pending')
            ->orderBy('installment_number')
            ->first();

        if ($nextPending) {
            $nextPending->update([
                'status' => 'paid',
                'paid_date' => now()
            ]);
        }

        // التحقق من اكتمال جميع الأقساط
        $remainingPending = $installmentPlan->installmentPayments()
            ->where('status', 'pending')
            ->count();

        if ($remainingPending == 0) {
            $installmentPlan->update(['status' => 'completed']);
        }
    }

    // جلب تقرير المبيعات اليومية
    public function dailyReport(Request $request)
    {
        $date = $request->date ?? now()->toDateString();
        
        $sales = Sale::whereDate('date', $date)->get();
        
        $totalCash = $sales->where('sale_type', 'cash')->sum('total');
        $totalCredit = $sales->where('sale_type', 'credit')->sum('total');
        $totalInstallment = $sales->where('sale_type', 'installment')->sum('total');
        $totalVisa = $sales->where('sale_type', 'visa')->sum('total');
        $totalWallet = $sales->where('sale_type', 'wallet')->sum('total');
        
        return response()->json([
            'success' => true,
            'date' => $date,
            'total_sales' => $sales->sum('total'),
            'total_cash' => $totalCash,
            'total_credit' => $totalCredit,
            'total_installment' => $totalInstallment,
            'total_visa' => $totalVisa,
            'total_wallet' => $totalWallet,
            'sales_count' => $sales->count()
        ]);
    }
// إضافة دفعة لفاتورة تقسيط
public function addInstallmentPayment(Request $request, $id)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01',
        'payment_method' => 'required|in:cash,visa,wallet,bank_transfer'
    ]);

    $sale = Sale::findOrFail($id);

    // التأكد أن الفاتورة من نوع تقسيط
    if ($sale->sale_type != 'installment') {
        return response()->json([
            'success' => false,
            'message' => 'هذه الفاتورة ليست من نوع تقسيط'
        ], 400);
    }

    // جلب خطة التقسيط
    $installmentPlan = $sale->installmentPlan;
    
    if (!$installmentPlan) {
        return response()->json([
            'success' => false,
            'message' => 'لا توجد خطة تقسيط لهذه الفاتورة'
        ], 404);
    }

    // جلب أول قسط غير مدفوع
    $nextPayment = $installmentPlan->installmentPayments()
        ->where('status', 'pending')
        ->orderBy('installment_number')
        ->first();

    if (!$nextPayment) {
        return response()->json([
            'success' => false,
            'message' => 'لا توجد أقساط مستحقة أو جميع الأقساط مدفوعة'
        ], 400);
    }

    // التحقق من المبلغ
    if ($request->amount < $nextPayment->amount) {
        return response()->json([
            'success' => false,
            'message' => 'المبلغ المدفوع أقل من قيمة القسط',
            'required' => $nextPayment->amount,
            'paid' => $request->amount
        ], 400);
    }

    try {
        DB::beginTransaction();

        // تسجيل الدفعة في جدول المدفوعات
        $payment = \App\Models\SalePayment::create([
            'sale_id' => $sale->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_date' => now(),
            'notes' => 'دفعة قسط رقم ' . $nextPayment->installment_number
        ]);

        // تحديث القسط إلى مدفوع
        $nextPayment->update([
            'status' => 'paid',
            'paid_date' => now()
        ]);

        // تحديث الفاتورة
        $newPaid = $sale->paid + $request->amount;
        $newRemaining = $sale->total - $newPaid;

        $sale->update([
            'paid' => $newPaid,
            'remaining' => $newRemaining,
            'status' => $newRemaining <= 0 ? 'completed' : 'partial'
        ]);

        // تحديث خطة التقسيط
        $remainingAmount = $installmentPlan->remaining_amount - $nextPayment->amount;
        $installmentPlan->update([
            'remaining_amount' => $remainingAmount,
            'status' => $remainingAmount <= 0 ? 'completed' : 'active'
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم تسديد القسط رقم ' . $nextPayment->installment_number . ' بنجاح',
            'data' => [
                'installment_number' => $nextPayment->installment_number,
                'paid_amount' => $nextPayment->amount,
                'remaining_balance' => $newRemaining,
                'next_installment' => $installmentPlan->installmentPayments()
                    ->where('status', 'pending')
                    ->orderBy('installment_number')
                    ->first()
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء تسديد القسط',
            'error' => $e->getMessage()
        ], 500);
    }
}
}