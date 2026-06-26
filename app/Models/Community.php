<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Community extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'invite_code',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Generate a unique invite code before creating.
     */
    protected static function booted(): void
    {
        static::creating(function (Community $community) {
            $community->invite_code = strtoupper(Str::random(8));
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * All accepted members (including owner).
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'community_user')
                    ->withPivot('role', 'status', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Accepted members only.
     */
    public function acceptedMembers()
    {
        return $this->members()->wherePivot('status', 'accepted');
    }

    /**
     * Pending join requests (for private communities).
     */
    public function pendingMembers()
    {
        return $this->members()->wherePivot('status', 'pending');
    }

    /**
     * All books shared by accepted members (physical or both).
     */
    public function books()
    {
        $memberIds = $this->acceptedMembers()->pluck('users.id');
        return Book::whereIn('user_id', $memberIds)
                   ->whereIn('format', ['physical', 'both'])
                   ->with('user:id,name,avatar', 'tags', 'author');
    }

    public function bookRequests()
    {
        return $this->hasMany(BookRequest::class);
    }
}
