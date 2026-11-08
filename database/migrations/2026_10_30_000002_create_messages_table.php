<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 通常投稿と返信を保存するメッセージテーブルを定義する。
 */
return new class extends Migration
{
    /**
     * 通常投稿・返信のテーブルと、版を正の整数に制限する制約を作成する。
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->string('body', 1000);
            $table->unsignedInteger('version')->default(1);
            $table->dateTime('created_at');
            $table->dateTime('edited_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->index(['channel_id', 'parent_message_id', 'created_at', 'id'], 'messages_channel_parent_created_index');
        });
        DB::statement('ALTER TABLE messages ADD CONSTRAINT messages_positive_version CHECK (version > 0)');
    }

    /**
     * メッセージテーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
