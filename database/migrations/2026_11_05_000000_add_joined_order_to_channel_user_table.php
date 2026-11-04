<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_user', function (Blueprint $table) {
            $table->unsignedBigInteger('joined_order')->nullable();
        });

        // 過去の参加順は復元できないため、既存行だけはID順で補完します。
        DB::statement('UPDATE channel_user AS membership
            JOIN (SELECT channel_id, user_id,
                ROW_NUMBER() OVER (ORDER BY channel_id, user_id) AS sequence_number
                FROM channel_user) AS ordered
            ON membership.channel_id = ordered.channel_id AND membership.user_id = ordered.user_id
            SET membership.joined_order = ordered.sequence_number');

        // 複合主キーを維持し、自動採番列には独立した一意キーを使います。
        DB::statement('ALTER TABLE channel_user
            ADD UNIQUE KEY channel_user_joined_order_unique (joined_order),
            MODIFY joined_order BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        Schema::table('channel_user', function (Blueprint $table) {
            $table->dropColumn('joined_order');
        });
    }
};
