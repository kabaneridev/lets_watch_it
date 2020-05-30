<?php

namespace Common\Domains;

use App\User;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Common\Domains\CustomDomain
 * @property string $host
 * @method Builder forUser(int $userId)
 * @mixin Eloquent
 */
class CustomDomain extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'global' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit query to only custom domains specified user has acess to.
     * @param Builder $query
     * @param int $userId
     * @return Builder
     */
    public function scopeForUser(Builder $query, $userId)
    {
        return $query->where('user_id', $userId)->orWhere('global', true);
    }
}
