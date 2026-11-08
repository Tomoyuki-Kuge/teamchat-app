<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * 作成日時と削除日時を表示用のJSTへ変換する共通機能を提供する。
 */
trait HasJstDates
{
    // 保存値はUTCのままにして、表示用の日時だけをJSTへ変換します。
    protected function createdAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->created_at?->copy()->setTimezone('Asia/Tokyo'));
    }

    /**
     * 元の削除日時を変更せず、JSTに変換するアクセサを定義する。
     */
    protected function deletedAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->deleted_at?->copy()->setTimezone('Asia/Tokyo'));
    }
}
