<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Mulai query dengan relasi
        $query = Transaction::with(['customer', 'details.product']);

        // LOGIKA BERDASARKAN ROLE
        if ($user->role === 'kasir') {
            // Kasir bisa lihat SEMUA transaksi (tidak ada filter user_id)
        } else {
            // Pelanggan bisa lihat transaksi berdasarkan user_id ATAU customer_name
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('customer_name', $user->name);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by confirmation status
        if ($request->has('is_confirmed')) {
            $isConfirmed = filter_var($request->is_confirmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isConfirmed !== null) {
                $query->where('is_confirmed', $isConfirmed);
            }
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Search by invoice number or customer name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $transactions = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request)
    {
        // Validasi dasar
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ], [
            'items.required' => 'Minimal ada 1 item produk',
            'items.*.product_id.required' => 'Product ID wajib diisi',
            'items.*.product_id.exists' => 'Produk tidak ditemukan',
            'items.*.quantity.required' => 'Quantity wajib diisi',
            'items.*.quantity.integer' => 'Quantity harus angka',
            'items.*.quantity.min' => 'Quantity minimal 1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $items = $request->items;
            $totalAmount = 0;
            $itemDetails = [];

            // Calculate total and validate stock
            foreach ($items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Produk dengan ID {$item['product_id']} tidak ditemukan");
                }

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->stock}");
                }

                $price = (float) $product->price;
                $subtotal = $price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemDetails[] = [
                    'product_id' => $product->id,
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            // Generate unique codes
            $invoiceNumber = Transaction::generateInvoiceNumber();
            $transactionCode = 'TRX/' . date('Ymd') . '/' . str_pad(Transaction::count() + 1, 6, '0', STR_PAD_LEFT);

            // Dapatkan user yang sedang login
            $user = auth()->user();
            
            // Tentukan tipe transaksi berdasarkan role
            if ($user->role === 'kasir') {
                // Transaksi oleh kasir (POS offline)
                $status = 'success';
                $isConfirmed = true;
                $cashierId = $user->id;
                $customerId = $user->id;
                $paidAmount = $request->paid_amount ?? $totalAmount;
                $changeAmount = $paidAmount - $totalAmount;
                $paymentMethod = $request->payment_method ?? 'cash';
                $customerName = $request->customer_name ?? $user->name;
            } else {
                // Transaksi oleh pelanggan (online order)
                $status = 'success';
                $isConfirmed = false;
                $cashierId = null;
                $customerId = $user->id;
                $paidAmount = $totalAmount;
                $changeAmount = 0;
                $paymentMethod = 'cash';
                $customerName = $user->name; 
            }

            // Create transaction
            $transaction = Transaction::create([
                'cashier_id' => $cashierId,
                'user_id' => $customerId, 
                'invoice_number' => $invoiceNumber,
                'transaction_code' => $transactionCode,
                'customer_name' => $customerName,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'change_amount' => $changeAmount,
                'payment_method' => $paymentMethod,
                'status' => $status,
                'is_confirmed' => $isConfirmed,
            ]);

            // Create transaction details
            foreach ($itemDetails as $detail) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $detail['product_id'],
                    'price' => $detail['price'],
                    'quantity' => $detail['quantity'],
                    'subtotal' => $detail['subtotal'],
                ]);

                // Update product stock
                $product = Product::find($detail['product_id']);
                $product->decrement('stock', $detail['quantity']);
            }

            DB::commit();

            // Load hanya relasi yang aman
            $transaction->load(['customer', 'details.product']);

            return response()->json([
                'success' => true,
                'message' => $isConfirmed 
                    ? 'Transaksi berhasil dibuat' 
                    : 'Pesanan berhasil dibuat! Menunggu konfirmasi kasir.',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show($id)
    {
        $user = auth()->user();
        
        if ($user->role === 'kasir') {
            // Kasir bisa lihat semua transaksi
            $transaction = Transaction::with(['customer', 'details.product'])
                ->findOrFail($id);
        } else {
            // Pelanggan hanya lihat transaksinya sendiri
            $transaction = Transaction::where('user_id', $user->id)
                ->with(['customer', 'details.product'])
                ->findOrFail($id);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Confirm an online order (by cashier).
     */
    public function confirmOrder(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);
        
        if ($transaction->is_confirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi sudah dikonfirmasi'
            ], 400);
        }

        $transaction->update([
            'is_confirmed' => true,
            'cashier_id' => auth()->id(),
            'confirmed_at' => now(),
            'status' => 'success'
        ]);

        $transaction->load(['cashier', 'customer', 'details.product']);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dikonfirmasi',
            'data' => $transaction
        ]);
    }

    // 🔥 METHOD BARU: AMBIL TRANSAKSI BY ORDER_ID (PUBLIK & AMAN)
    public function getByOrderId(Request $request)
    {
        $orderId = $request->query('order_id');
        
        if (!$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'order_id required'
            ], 400);
        }

        // Ambil transaksi tanpa relasi Eloquent (pakai Query Builder)
        $transaction = DB::table('transactions')
            ->where('order_id', $orderId)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        // Ambil detail transaksi
        $details = DB::table('transaction_details as td')
            ->join('products as p', 'td.product_id', '=', 'p.id')
            ->select('p.name as product_name', 'td.quantity', 'td.price', 'td.subtotal')
            ->where('td.transaction_id', $transaction->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'invoice_number' => $transaction->invoice_number,
                'customer_name' => $transaction->customer_name,
                'total_amount' => (float) $transaction->total_amount,
                'payment_method' => $transaction->payment_method,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at,
                'details' => $details,
            ]
        ]);
    }
}