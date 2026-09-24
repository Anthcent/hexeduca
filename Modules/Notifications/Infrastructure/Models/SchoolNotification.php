<?php

namespace Modules\Notifications\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Module-owned table, deliberately separate from Laravel's `notifications`
 * table so this module never depends on the Users module's Notifiable model.
 */
class SchoolNotification extends Model
{
    use BelongsToTenant;

    protected $table = 'school_notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'recipient_id',
        'sender_id',
        'sender_name',
        'title',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForRecipient(Builder $query, int $recipientId, int $schoolId): Builder
    {
        return $query->where('recipient_id', $recipientId)->where('school_id', $schoolId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
