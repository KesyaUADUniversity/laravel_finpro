<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom role lama
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom role baru
            $table->enum('role', ['owner', 'kasir', 'user'])->default('user')->after('password');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'customer_service'])->default('customer_service')->after('password');
        });
    }
};