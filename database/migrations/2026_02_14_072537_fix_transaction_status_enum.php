<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'failed', 'completed', 'cancelled') NOT NULL DEFAULT 'completed'");
        
        
        DB::table('transactions')->where('status', 'completed')->update(['status' => 'success']);
        
        
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed'");
        DB::table('transactions')->where('status', 'success')->update(['status' => 'completed']);
    }
};