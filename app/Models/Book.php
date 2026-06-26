<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'author',
        'description',
        'published_at',
        'cover_image',
        'format',
        'download_link',
        'pages',
        'recommended_by',
        'loaned_to',
        'author_id',
        'reading_status',
        'summary',
        'reading_notes',
        'lent_by',
        'links',
    ];

    protected $casts = [
        'links' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function author()
    {
        return $this->belongsTo(Author::class);
    }
}
