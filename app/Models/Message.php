<?php

namespace App\Models;

use App\Models\Concerns\HasJstDates;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 通常投稿・返信の情報と関連、表示用日時を定義する。
 */
class Message extends Model
{
    use HasJstDates;

    const UPDATED_AT = null;

    protected $fillable = ['body'];

    protected $casts = ['created_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'version' => 'integer'];

    /**
     * 所属チャンネルへの関連定義を返す。
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * 投稿者への関連定義を返す。
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 返信先の親メッセージへの関連定義を返す。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    /**
     * このメッセージを親とする返信への関連定義を返す。
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }

    /**
     * 元の編集日時を変更せず、JSTに変換するアクセサを定義する。
     */
    protected function editedAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->edited_at?->copy()->setTimezone('Asia/Tokyo'));
    }
}
