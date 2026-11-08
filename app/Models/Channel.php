<?php

namespace App\Models;

use App\Models\Concerns\HasJstDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * チャンネルの情報・関連と取得条件を定義する。
 */
class Channel extends Model
{
    use HasFactory, HasJstDates;

    const UPDATED_AT = null;

    protected $fillable = ['name', 'description', 'is_private'];

    protected $hidden = ['active_name'];

    protected $casts = ['is_private' => 'boolean', 'created_at' => 'datetime', 'deleted_at' => 'datetime'];

    /**
     * チャンネル作成者への関連定義を返す。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * 所属メンバーへの関連定義を返す。
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * チャンネル内の通常投稿・返信への関連定義を返す。
     */
    public function messages(): HasMany
    {
        // 削除確認の集計では個別削除済みも含めます。
        return $this->hasMany(Message::class);
    }

    /**
     * 未削除のチャンネルに絞り込む。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('channels.deleted_at');
    }

    /**
     * 未削除で、公開または本人が所属するチャンネルに絞り込む。
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->active()->where(function (Builder $query) use ($user) {
            $query->where('is_private', false)
                ->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
        });
    }

    /**
     * 作成日時とIDの昇順に並べる。
     */
    public function scopeInCreationOrder(Builder $query): Builder
    {
        return $query->orderBy('channels.created_at')->orderBy('channels.id');
    }
}
