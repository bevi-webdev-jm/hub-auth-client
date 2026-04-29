<?php

namespace BeviWebdevJm\HubAuthClient\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal User stub with username field — simulates sto-online's User model.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'hub_user_id',
        'name',
        'email',
        'username',
        'password',
    ];

    protected $hidden = ['password'];
}
