<?php

namespace App\Models;

use App\Models\Concerns\HasJstDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory, HasJstDates;

    const UPDATED_AT = null;

    protected $fillable = ['name', 'description', 'is_private'];

    protected $hidden = ['active_name'];

    protected $casts = ['is_private' => 'boolean', 'created_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function messages(): HasMany
    {
        // 削除確認の集計では個別削除済みも含めます。
        return $this->hasMany(Message::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('channels.deleted_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->active()->where(function (Builder $query) use ($user) {
            $query->where('is_private', false)
                ->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
        });
    }

    public function scopeInCreationOrder(Builder $query): Builder
    {
        return $query->orderBy('channels.created_at')->orderBy('channels.id');
    }
}
