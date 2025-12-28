<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $appends = ['profile_photo_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'handle',
        'google_id',
        'apple_id',
        'address_line_1',
        'postcode',
        'avatar_path',
        'avatar_url',
        'signup_device_location',
        'trust_score',
        'hauls_hosted',
        'hauls_joined',
        'default_pickup_image_path',
        'default_pickup_instructions',
        'default_payment_instructions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the runs for the user.
     */
    public function runs()
    {
        return $this->hasMany(Run::class);
    }

    /**
     * Get the commitments for the user.
     */
    public function commitments()
    {
        return $this->hasMany(RunCommitment::class);
    }

    /**
     * Get the users that this user is following.
     */
    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /**
     * Get the users that are following this user.
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * Get the device tokens for the user.
     */
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get the count of hauls hosted by this user.
     */
    public function getHaulsHostedAttribute()
    {
        return $this->runs()->count();
    }

    /**
     * Get the count of hauls joined by this user.
     */
    public function getHaulsJoinedAttribute()
    {
        // Count distinct runs that the user has commitments for
        return $this->commitments()
            ->join('run_items', 'run_commitments.run_item_id', '=', 'run_items.id')
            ->distinct('run_items.run_id')
            ->count('run_items.run_id');
    }
}
