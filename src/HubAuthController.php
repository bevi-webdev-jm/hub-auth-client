<?php

namespace BeviWebdevJm\HubAuthClient;

use BeviWebdevJm\HubAuthClient\Contracts\ResolvesHubUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class HubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('hub')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $hubUser = Socialite::driver('hub')->user();

        $localUser = app(ResolvesHubUser::class)->resolve($hubUser);

        Auth::login($localUser, remember: true);

        return redirect(config('hub-auth.redirect_after_login', '/home'));
    }
}
