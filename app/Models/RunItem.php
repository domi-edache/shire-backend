<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RunItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'run_id',
        'type',
        'title',
        'cost',
        'units_total',
        'units_filled',
    ];

    /**
     * Get the run that owns the run item.
     */
    public function run()
    {
        return $this->belongsTo(Run::class);
    }

    /**
     * Get the commitments for the run item.
     */
    public function commitments()
    {
        return $this->hasMany(RunCommitment::class);
    }

    /**
     * Get the percent filled attribute.
     *
     * @return float
     */
    public function getPercentFilledAttribute()
    {
        if ($this->units_total == 0) {
            return 0;
        }

        return ($this->units_filled / $this->units_total) * 100;
    }
}
