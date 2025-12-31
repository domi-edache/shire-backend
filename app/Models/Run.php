<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Run extends Model
{
    use SoftDeletes;
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
        'pickup_instructions',
        'payment_instructions',
        'runner_fee',
        'runner_fee_type',
        'location',
        'is_taking_requests',
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
            'is_taking_requests' => 'boolean',
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

    /**
     * Get the activities for the run.
     */
    public function activities()
    {
        return $this->hasMany(RunActivity::class);
    }

    /**
     * Get all commitments for the run through its items.
     */
    public function commitments()
    {
        return $this->hasManyThrough(RunCommitment::class, RunItem::class);
    }
}
