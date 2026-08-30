<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\BankTransferSubmission;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /** Backs the KPI row + Needs Attention panel on the dashboard's home screen. */
    public function summary()
    {
        $sinceLastPeriod = Carbon::now()->subDays(7);

        return response()->json([
            'kpis' => [
                'total_revenue' => (float) Order::where('payment_status', 'paid')->sum('total'),
                'total_orders' => Order::count(),
                'customers' => Customer::count(),
                'products' => Product::count(),
            ],
            'order_overview' => Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'needs_attention' => [
                'payment_failures' => Order::where('payment_status', 'failed')->count(),
                'bank_transfers_pending' => BankTransferSubmission::where('verification_status', 'pending')->count(),
                'low_stock_products' => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')->where('stock_quantity', '>', 0)->count(),
                'out_of_stock_products' => Product::where('stock_quantity', '<=', 0)->where('allow_backorders', false)->count(),
            ],
            'payment_methods' => Payment::selectRaw('gateway, count(*) as orders_count, sum(amount) as revenue')
                ->where('status', 'paid')
                ->groupBy('gateway')
                ->get(),
        ]);
    }

    /** Sales Overview chart data — matches the 7/30/90/365-day range toggle in the UI. */
    public function salesSeries(\Illuminate\Http\Request $request)
    {
        $days = (int) $request->query('range', 7);
        $from = Carbon::now()->subDays($days);

        $rows = Order::selectRaw('DATE(created_at) as day, sum(total) as sales, count(*) as orders_count')
            ->where('created_at', '>=', $from)
            ->where('payment_status', 'paid')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json($rows);
    }
}
