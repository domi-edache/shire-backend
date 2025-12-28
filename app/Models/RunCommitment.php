<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RunCommitment extends Model
{
    use SoftDeletes;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'run_item_id',
        'user_id',
        'quantity',
        'total_amount',
        'status',
        'payment_status',
    ];

    /**
     * Get the run item that owns the commitment.
     */
    public function item()
    {
        return $this->belongsTo(RunItem::class, 'run_item_id');
    }

    /**
     * Get the user that made the commitment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
