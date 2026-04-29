<?php

namespace BeviWebdevJm\HubAuthClient\Tests\Feature;

use BeviWebdevJm\HubAuthClient\HubProvider;
use BeviWebdevJm\HubAuthClient\Resolvers\DefaultHubUserResolver;
use BeviWebdevJm\HubAuthClient\Tests\Stubs\User;
use BeviWebdevJm\HubAuthClient\Tests\Stubs\UserWithoutUsername;
use BeviWebdevJm\HubAuthClient\Tests\TestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class HubAuthTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------------
    // Driver registration
    // ---------------------------------------------------------------------------

    public function test_hub_socialite_driver_is_registered(): void
    {
        $driver = Socialite::driver('hub');

        $this->assertInstanceOf(HubProvider::class, $driver);
    }

    public function test_login_hub_redirects_to_hub_oauth_url(): void
    {
        $response = $this->get('/login/hub');

        $response->assertRedirectContains('http://hub.test/oauth/authorize');
    }

    // ---------------------------------------------------------------------------
    // DefaultHubUserResolver — first-time user
    // ---------------------------------------------------------------------------

    public function test_resolver_creates_new_user_on_first_login(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        $hubUser = $this->makeHubUser(id: 42, name: 'Jane Doe', email: 'jane@example.com');

        $resolver = new DefaultHubUserResolver();
        $user = $resolver->resolve($hubUser);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('42', $user->hub_user_id);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertNull($user->password);
    }

    public function test_resolver_derives_username_when_model_has_username_field(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        $hubUser = $this->makeHubUser(id: 1, name: 'Alice', email: 'alice@example.com');

        $resolver = new DefaultHubUserResolver();
        $resolver->resolve($hubUser);

        $this->assertDatabaseHas('users', ['username' => 'alice']);
    }

    public function test_resolver_increments_username_on_collision(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        User::create([
            'hub_user_id' => '99',
            'name'        => 'Existing',
            'email'       => 'alice2@example.com',
            'username'    => 'alice',
            'password'    => null,
        ]);

        $hubUser = $this->makeHubUser(id: 100, name: 'New Alice', email: 'alice@example.com');

        $resolver = new DefaultHubUserResolver();
        $resolver->resolve($hubUser);

        $this->assertDatabaseHas('users', ['username' => 'alice1']);
    }

    public function test_resolver_skips_username_when_model_has_no_username_field(): void
    {
        $this->app['config']->set('auth.providers.users.model', UserWithoutUsername::class);

        $hubUser = $this->makeHubUser(id: 5, name: 'Bob', email: 'bob@example.com');

        $resolver = new DefaultHubUserResolver();
        $user = $resolver->resolve($hubUser);

        $this->assertNull($user->username ?? null);
        $this->assertDatabaseHas('users', ['hub_user_id' => '5']);
    }

    // ---------------------------------------------------------------------------
    // DefaultHubUserResolver — returning user
    // ---------------------------------------------------------------------------

    public function test_resolver_returns_existing_user_on_repeat_login(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        User::create([
            'hub_user_id' => '77',
            'name'        => 'Existing User',
            'email'       => 'existing@example.com',
            'username'    => 'existinguser',
            'password'    => null,
        ]);

        $hubUser = $this->makeHubUser(id: 77, name: 'Existing User', email: 'existing@example.com');

        $resolver = new DefaultHubUserResolver();
        $user = $resolver->resolve($hubUser);

        $this->assertDatabaseCount('users', 1);
        $this->assertEquals('77', $user->hub_user_id);
    }

    // ---------------------------------------------------------------------------
    // Callback route integration (mocked Socialite)
    // ---------------------------------------------------------------------------

    public function test_callback_logs_in_new_user_and_redirects(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        $mockSocialiteUser = $this->makeHubUser(id: 10, name: 'Test User', email: 'test@example.com');

        Socialite::shouldReceive('driver->user')->once()->andReturn($mockSocialiteUser);

        $response = $this->get('/login/hub/callback');

        $response->assertRedirect('/home');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['hub_user_id' => '10']);
    }

    public function test_callback_logs_in_existing_user_without_creating_duplicate(): void
    {
        $this->app['config']->set('auth.providers.users.model', User::class);

        User::create([
            'hub_user_id' => '20',
            'name'        => 'Returning',
            'email'       => 'returning@example.com',
            'username'    => 'returning',
            'password'    => null,
        ]);

        $mockSocialiteUser = $this->makeHubUser(id: 20, name: 'Returning', email: 'returning@example.com');

        Socialite::shouldReceive('driver->user')->once()->andReturn($mockSocialiteUser);

        $this->get('/login/hub/callback');

        $this->assertDatabaseCount('users', 1);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    protected function makeHubUser(int $id, string $name, string $email): SocialiteUser
    {
        $user = new SocialiteUser();
        $user->map(['id' => $id, 'name' => $name, 'email' => $email]);

        return $user;
    }
}
