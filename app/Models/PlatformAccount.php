<?php

namespace AppModels;

use DatabaseFactoriesPlatformAccountFactory;
use IlluminateDatabaseEloquentAttributesFillable;
use IlluminateDatabaseEloquentAttributesHidden;
use IlluminateDatabaseEloquentFactoriesHasFactory;
use IlluminateFoundationAuthUser as Authenticatable;
use IlluminateNotificationsNotifiable;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class PlatformAccount extends Authenticatable
{
    /** @use HasFactory<PlatformAccountFactory> */
    use HasFactory, HasUlids, Notifiable;

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
};
