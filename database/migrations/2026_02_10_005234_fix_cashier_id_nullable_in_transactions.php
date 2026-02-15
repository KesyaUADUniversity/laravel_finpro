<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Tambah kolom baru yang nullable
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('cashier_id_temp')->nullable();
        });
        
        // 2. Copy data dari kolom lama ke kolom baru
        DB::statement('UPDATE transactions SET cashier_id_temp = cashier_id');
        
        // 3. Hapus foreign key dan kolom lama
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->dropColumn('cashier_id');
        });
        
        // 4. Rename kolom temp ke nama asli
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('cashier_id_temp', 'cashier_id');
        });
        
        // 5. Tambahkan foreign key kembali (dengan ON DELETE SET NULL)
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('cashier_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        // Reverse process
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('cashier_id_old');
        });
        
        DB::statement('UPDATE transactions SET cashier_id_old = cashier_id');
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->dropColumn('cashier_id');
        });
        
        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('cashier_id_old', 'cashier_id');
            $table->foreign('cashier_id')->references('id')->on('users');
        });
    }
};