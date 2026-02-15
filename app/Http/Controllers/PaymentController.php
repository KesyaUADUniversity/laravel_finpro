<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; 
use App\Models\Transaction;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        // Nonaktifkan error reporting untuk warning Midtrans
        error_reporting(E_ALL & ~E_WARNING);

        try {
            $orderId = $request->input('order_id') ?: 'POS-' . time() . '-' . rand(100, 999);
            
            $grossAmount = (float) ($request->input('gross_amount') ?: 1000);
            $customerName = $request->input('customer_name') ?: 'Pelanggan Umum';
            $customerEmail = $request->input('customer_email') ?: 'default@warunggenz.id';
            $customerPhone = $request->input('customer_phone') ?: '08000000000';

            \Midtrans\Config::$serverKey = 'Mid-server-X48Uwr_xqpUvkHq8Ne-M4hSO';
            \Midtrans\Config::$isProduction = false;
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            \Midtrans\Config::$curlOptions = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 60,
            ];

            stream_context_set_default([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $callbackUrl = 'http://localhost:5173/customer/receipt/' . $orderId;

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $grossAmount,
                ],
                'item_details' => [[
                    'id' => 'ITEM-1',
                    'price' => $grossAmount,
                    'quantity' => 1,
                    'name' => 'Pembayaran Warung Gen Z'
                ]],
                'customer_details' => [
                    'first_name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                ],
                'enabled_payments' => ['gopay', 'dana', 'ovo', 'bca_va'],
                'callbacks' => [
                    'finish' => $callbackUrl,
                ]
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);

            Transaction::create([
                'order_id' => $orderId,
                'invoice_number' => 'INV/' . date('Ymd') . '/' . strtoupper(uniqid()),
                'transaction_code' => 'TRX/' . date('Ymd') . '/' . strtoupper(uniqid()),
                'user_id' => auth()->check() ? auth()->id() : 1,
                'customer_name' => $customerName,
                'total_amount' => $grossAmount,
                'paid_amount' => $grossAmount,
                'change_amount' => 0,
                'payment_method' => 'midtrans',
                'status' => 'pending',
                'is_confirmed' => 0,
            ]);

            return response()->json(['token' => $snapToken]);

        } catch (\Exception $e) {
            Log::error('Payment Error', ['message' => $e->getMessage()]); 
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function notification(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $statusCode = $request->input('status_code');

            if (!$orderId) {
                return response()->json(['error' => 'order_id required'], 400);
            }

            $transaction = Transaction::where('order_id', $orderId)->first();
            if (!$transaction) {
                return response()->json(['error' => 'Transaction not found'], 404);
            }

            $statusMap = [
                '200' => 'success',
                '201' => 'pending',
                '406' => 'failed',
                '407' => 'cancelled',
            ];
            $newStatus = $statusMap[$statusCode] ?? 'failed';

            $transaction->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

            return response()->json(['message' => 'OK']);

        } catch (\Exception $e) {
            Log::error('Notification Error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook failed'], 500);
        }
    }
}