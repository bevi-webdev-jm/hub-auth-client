<?php

namespace BeviWebdevJm\HubAuthClient\Tests\Stubs;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal User stub WITHOUT username field — simulates a standard Laravel app.
 */
class UserWithoutUsername extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'hub_user_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = ['password'];
}
