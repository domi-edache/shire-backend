<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'store_name',
        'status',
        'expires_at',
        'pickup_image_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the run.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the run.
     */
    public function items()
    {
        return $this->hasMany(RunItem::class);
    }

    /**
     * Scope a query to only include runs near a given location.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $lat
     * @param float $long
     * @param int $radiusMeters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNearby($query, $lat, $long, $radiusMeters = 5000)
    {
        return $query->whereRaw(
            "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
            [$long, $lat, $radiusMeters]
        );
    }

    /**
     * Get the chats for the run.
     */
    public function chats()
    {
        return $this->hasMany(RunChat::class);
    }
}
