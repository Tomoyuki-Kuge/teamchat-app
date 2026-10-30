<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasJstDates
{
    // 保存値はUTCのままにして、表示用の日時だけをJSTへ変換します。
    protected function createdAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->created_at?->copy()->setTimezone('Asia/Tokyo'));
    }

    protected function deletedAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->deleted_at?->copy()->setTimezone('Asia/Tokyo'));
    }
}
