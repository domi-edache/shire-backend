<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfileChange extends Model
{
    protected $fillable = [
        'user_id',
        'field',
        'old_value',
        'new_value',
        'trigger',
    ];

    /**
     * Get the user that owns the profile change.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
