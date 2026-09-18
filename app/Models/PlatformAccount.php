<?php

namespace App\Models;

use Database\Factories\PlatformAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class PlatformAccount extends Authenticatable
{
    /** @use HasFactory<PlatformAccountFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected $connection = 'central';

    protected $table = 'platform_accounts';

    /**
     * Get the model attributes that should be cast.
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
}
