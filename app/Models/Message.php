<?php

namespace App\Models;

use App\Models\Concerns\HasJstDates;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    use HasJstDates;

    const UPDATED_AT = null;

    protected $fillable = ['body'];

    protected $casts = ['created_at' => 'datetime', 'edited_at' => 'datetime', 'deleted_at' => 'datetime', 'version' => 'integer'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }

    protected function editedAtJst(): Attribute
    {
        return Attribute::make(get: fn () => $this->edited_at?->copy()->setTimezone('Asia/Tokyo'));
    }
}
