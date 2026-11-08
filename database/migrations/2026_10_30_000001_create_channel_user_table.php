<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * チャンネルへの所属を保存する中間テーブルを定義する。
 */
return new class extends Migration
{
    /**
     * 所属の重複を防ぐ複合主キー付きの中間テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('channel_user', function (Blueprint $table) {
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['channel_id', 'user_id']);
        });
    }

    /**
     * チャンネル所属の中間テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_user');
    }
};
