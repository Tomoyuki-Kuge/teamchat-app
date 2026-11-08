<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * パスワード再設定トークンの保存用テーブルを定義する。
 */
return new class extends Migration
{
    /**
     * メールを主キーとするpassword_reset_tokensテーブルを作成する。
     *
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * password_reset_tokensテーブルが存在する場合に削除する。
     *
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
