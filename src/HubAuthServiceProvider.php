<?php

namespace BeviWebdevJm\HubAuthClient;

use BeviWebdevJm\HubAuthClient\Contracts\ResolvesHubUser;
use BeviWebdevJm\HubAuthClient\Resolvers\DefaultHubUserResolver;
use Illuminate\Support\ServiceProvider;

class HubAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/hub-auth.php', 'hub-auth');

        $this->app->bind(ResolvesHubUser::class, DefaultHubUserResolver::class);
    }

    public function boot(): void
    {
        // Push credentials into services.hub after all providers have registered,
        // ensuring env values are resolved before Socialite reads them.
        $this->app->booted(function (): void {
            config([
                'services.hub' => [
                    'client_id'     => config('hub-auth.client_id'),
                    'client_secret' => config('hub-auth.client_secret'),
                    'redirect'      => config('hub-auth.redirect'),
                ],
            ]);
        });

        // Register the 'hub' Socialite driver
        $this->app->resolving(\Laravel\Socialite\Contracts\Factory::class, function ($socialite): void {
            $hubUrl = config('hub-auth.hub_url', 'http://localhost:8000');

            $socialite->extend('hub', function ($app) use ($hubUrl) {
                $config = $app['config']['services.hub'];

                return new HubProvider(
                    $app['request'],
                    $config['client_id'] ?? '',
                    $config['client_secret'] ?? '',
                    $config['redirect'] ?? '',
                    $hubUrl,
                );
            });
        });

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/hub-auth.php' => config_path('hub-auth.php'),
            ], 'hub-auth-config');
        }
    }
}
