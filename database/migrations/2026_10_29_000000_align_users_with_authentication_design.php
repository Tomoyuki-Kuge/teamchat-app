<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password', 'password_hash');
        });

        DB::statement('ALTER TABLE users MODIFY name VARCHAR(30) NOT NULL, MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NOT NULL, MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password_hash', 'password');
        });
    }
};
