<?php

namespace Noros\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRating extends Model
{
    protected $fillable = [
        'post_id',
        'visitor_hash',
        'ip_hash',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
