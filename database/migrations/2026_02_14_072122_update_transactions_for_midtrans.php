<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Tambah kolom order_id (unik untuk Midtrans)
            $table->string('order_id')->nullable()->after('invoice_number');
            
            // Tambah kolom payment_token
            $table->text('payment_token')->nullable()->after('total_amount');
            
            // Ubah kolom status
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
            
            // Perluas payment_method
            DB::statement("ALTER TABLE transactions MODIFY COLUMN payment_method ENUM('cash','debit','credit','transfer','midtrans') NOT NULL");
        });
        
        // Tambah unique constraint setelah data lama di-handle
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('order_id');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropColumn('order_id');
            $table->dropColumn('payment_token');
        });
    }
};