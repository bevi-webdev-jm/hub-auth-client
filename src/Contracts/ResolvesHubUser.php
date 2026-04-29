<?php

namespace BeviWebdevJm\HubAuthClient\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Two\User as SocialiteUser;

interface ResolvesHubUser
{
    public function resolve(SocialiteUser $hubUser): Authenticatable;
}
