<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookRequest extends Model
{
    protected $fillable = [
        'community_id',
        'requester_id',
        'book_id',
        'message',
        'status',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class)->with('user:id,name,avatar', 'author');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function community()
    {
        return $this->belongsTo(Community::class);
    }
}
