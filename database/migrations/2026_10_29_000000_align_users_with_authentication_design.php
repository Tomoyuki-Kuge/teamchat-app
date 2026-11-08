<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 社員アカウントの列定義を認証設計に合わせる。
 */
return new class extends Migration
{
    /**
     * パスワード列を改名し、表示名の長さとメールの照合順序を変更する。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password', 'password_hash');
        });

        DB::statement('ALTER TABLE users MODIFY name VARCHAR(30) NOT NULL, MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin NOT NULL');
    }

    /**
     * 表示名・メールの列定義とパスワード列名を変更前に戻す。
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY name VARCHAR(255) NOT NULL, MODIFY email VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('password_hash', 'password');
        });
    }
};
