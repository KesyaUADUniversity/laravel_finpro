<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Generate sales report by date range.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function salesReport(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ], [
            'start_date.required' => 'Tanggal mulai wajib diisi',
            'end_date.required' => 'Tanggal akhir wajib diisi',
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Total sales
        $totalSales = DB::table('transactions')
            ->where('status', 'success')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('total_amount');

        // Total transactions
        $totalTransactions = DB::table('transactions')
            ->where('status', 'success')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        // Average transaction value
        $avgTransactionValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

        // Top selling products
        $topProducts = DB::table('transaction_details as td')
            ->join('products as p', 'td.product_id', '=', 'p.id')
            ->join('transactions as t', 'td.transaction_id', '=', 't.id')
            ->where('t.status', 'success')
            ->whereDate('t.created_at', '>=', $startDate)
            ->whereDate('t.created_at', '<=', $endDate)
            ->select(
                'p.id as product_id',
                'p.name as product_name',
                DB::raw("CONCAT('PROD-', LPAD(p.id, 3, '0')) as product_code"),
                DB::raw('SUM(td.quantity) as total_sold'),
                DB::raw('SUM(td.subtotal) as total_revenue')
            )
            ->groupBy('p.id', 'p.name')
            ->orderBy('total_sold', 'desc')
            ->limit(10)
            ->get();

        // Sales by day
        $salesByDay = DB::table('transactions')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total_amount) as total_sales')
            )
            ->where('status', 'success')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'summary' => [
                    'total_sales' => (int) $totalSales,
                    'total_sales_formatted' => 'Rp ' . number_format($totalSales, 0, ',', '.'),
                    'total_transactions' => $totalTransactions,
                    'average_transaction_value' => round($avgTransactionValue, 0),
                    'average_transaction_value_formatted' => 'Rp ' . number_format(round($avgTransactionValue, 0), 0, ',', '.'),
                ],
                'top_products' => $topProducts,
                'sales_by_day' => $salesByDay,
            ]
        ]);
    }

    /**
     * Generate stock report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockReport(Request $request)
    {
        $query = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->select(
                'p.id',
                'p.category_id',
                'p.name',
                'p.description',
                'p.price',
                'p.stock',
                'p.status',
                'c.name as category_name'
            );

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('p.category_id', $request->category_id);
        }

        // Filter low stock only
        if ($request->has('low_stock') && $request->low_stock == 'true') {
            $threshold = $request->get('threshold', 10);
            $query->where('p.stock', '<=', $threshold);
        }

        $products = $query->get();

        $totalProducts = $products->count();
        $totalStock = $products->sum('stock');
        $lowStockCount = $products->where('stock', '<=', 10)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_products' => $totalProducts,
                    'total_stock' => $totalStock,
                    'low_stock_count' => $lowStockCount,
                ],
                'products' => $products
            ]
        ]);
    }

    /**
     * Generate revenue report by category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revenueByCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $revenueByCategory = DB::table('transaction_details as td')
            ->join('products as p', 'td.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('transactions as t', 'td.transaction_id', '=', 't.id')
            ->where('t.status', 'success')
            ->whereDate('t.created_at', '>=', $startDate)
            ->whereDate('t.created_at', '<=', $endDate)
            ->select(
                'p.category_id',
                'c.name as category_name',
                DB::raw('SUM(td.quantity) as total_items_sold'),
                DB::raw('SUM(td.subtotal) as total_revenue')
            )
            ->groupBy('p.category_id', 'c.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $totalRevenue = $revenueByCategory->sum('total_revenue');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'total_revenue' => (int) $totalRevenue,
                'total_revenue_formatted' => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
                'categories' => $revenueByCategory
            ]
        ]);
    }
}