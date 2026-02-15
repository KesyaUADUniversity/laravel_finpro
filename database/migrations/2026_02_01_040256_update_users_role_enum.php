<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // ✅ Pastikan 'DB' sudah di-import di atas
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'kasir', 'user') NOT NULL DEFAULT 'user'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'customer_service') NOT NULL DEFAULT 'customer_service'");
    }
};