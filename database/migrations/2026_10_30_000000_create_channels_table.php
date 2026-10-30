<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->collation('utf8mb4_0900_bin');
            $table->string('description', 200)->nullable();
            $table->boolean('is_private');
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('deleted_at')->nullable();
            // 削除した名前は解放し、未削除の名前だけを原文のまま一意にします。
            $table->string('active_name', 50)->collation('utf8mb4_0900_bin')
                ->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN name ELSE NULL END');
            $table->unique('active_name', 'channels_active_name_unique');
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
