<?php

namespace BeviWebdevJm\HubAuthClient;

use BeviWebdevJm\HubAuthClient\Contracts\ResolvesHubUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class HubAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('hub')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $hubUser = Socialite::driver('hub')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login');
        }

        $localUser = app(ResolvesHubUser::class)->resolve($hubUser);

        Auth::login($localUser, remember: true);

        return redirect(config('hub-auth.redirect_after_login', '/home'));
    }
}
