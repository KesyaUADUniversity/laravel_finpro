<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_name' => 'required|string',
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

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception('Produk tidak ditemukan');
                }

                if ($product->stock < $item['quantity']) {
                    throw new \Exception('Stok '.$product->name.' tidak mencukupi');
                }

                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;

                $itemDetails[] = [
                    'product_id' => $product->id,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'subtotal' => $subtotal,
                ];
            }

            $invoiceNumber = Transaction::generateInvoiceNumber();
            $transactionCode = 'ONL/'.date('Ymd').'/'.str_pad(Transaction::count() + 1, 6, '0', STR_PAD_LEFT);

            $transaction = Transaction::create([
                'cashier_id' => null,
                'user_id' => auth()->id(),
                'invoice_number' => $invoiceNumber,
                'transaction_code' => $transactionCode,
                'customer_name' => $request->customer_name,
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'change_amount' => 0,
                'payment_method' => 'cod',
                'status' => 'menunggu_konfirmasi',
            ]);

            foreach ($itemDetails as $detail) {
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $detail['product_id'],
                    'price' => $detail['price'],
                    'quantity' => $detail['quantity'],
                    'subtotal' => $detail['subtotal'],
                ]);

                $product = Product::find($detail['product_id']);
                $product->decrement('stock', $detail['quantity']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat! Menunggu konfirmasi kasir.',
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
}