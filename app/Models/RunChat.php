<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunChat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'run_id',
        'user_id',
        'message',
        'image_path',
        'is_system_message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system_message' => 'boolean',
        ];
    }

    /**
     * Get the run that owns the chat message.
     */
    public function run()
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Get the user that sent the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
