# Hub Auth Client

A Laravel package that provides Single Sign-On (SSO) integration for Laravel applications via OAuth2, connecting them to a centralized Hub authentication server.

## Overview

This package handles the complete OAuth2 flow between your Laravel application and the Hub, including user synchronization, account linking by email, and automatic user provisioning on first login.

## Requirements

- PHP 8.0+
- Laravel 8.0–12.0
- Laravel Socialite 5.0+

## Installation

```bash
composer require bevi-webdev-jm/hub-auth-client
```

The package auto-registers its service provider via Laravel's package discovery.

## Setup

### 1. Publish the configuration

```bash
php artisan vendor:publish --tag=hub-auth-config
```

### 2. Add environment variables

```env
HUB_URL=https://hub.yourdomain.com
HUB_CLIENT_ID=your-client-id
HUB_CLIENT_SECRET=your-client-secret
HUB_REDIRECT_URI=https://yourapp.com/login/hub/callback
HUB_DEFAULT_ROLE=default_user
```

### 3. Add `hub_user_id` to your users table

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('hub_user_id')->nullable()->unique();
});
```

### 4. Add `hub_user_id` to your User model's `$fillable` array

```php
protected $fillable = [
    'name',
    'email',
    'hub_user_id',
    // ...
];
```

### 5. Add a login link to your template

```blade
<a href="{{ route('hub.login') }}">Login with Hub</a>
```

## Configuration

All options are in `config/hub-auth.php`:

| Key | ENV Variable | Default | Description |
|-----|-------------|---------|-------------|
| `hub_url` | `HUB_URL` | `http://localhost:8000` | Base URL of the Hub server |
| `client_id` | `HUB_CLIENT_ID` | — | OAuth2 client ID issued by Hub |
| `client_secret` | `HUB_CLIENT_SECRET` | — | OAuth2 client secret issued by Hub |
| `redirect` | `HUB_REDIRECT_URI` | — | Full callback URL of your application |
| `default_role` | `HUB_DEFAULT_ROLE` | `default_user` | Role assigned to new users (requires Spatie Permission) |
| `redirect_after_login` | — | `/home` | Where to redirect after successful login |
| `report_activity` | `HUB_REPORT_ACTIVITY` | `true` | Tell the Hub who is using this app, for its online list |
| `activity_every` | `HUB_ACTIVITY_EVERY` | `60` | Seconds between two reports of the same person (at least 30) |

## Activity reporting

The package adds a middleware to the `web` group that tells the Hub which
signed-in people are using this app, so the Hub's online list can show them as
"Online in <this app>" and count their active minutes. It runs after the
response is sent and reports each person at most once a minute, matched on the
Hub by email.

It uses the app's own client credentials: it asks the Hub for a
`client_credentials` token (kept until it expires) and posts to
`/api/hub/activity`. The Hub allows this grant for any confidential client that
signs people in with the authorization code grant, so no setup is needed on
either side. A Hub that cannot be reached is skipped quietly and tried again on
the next report. Set `HUB_REPORT_ACTIVITY=false` to turn it off.

## Authentication Flow

1. User visits `/login/hub` → redirected to Hub's OAuth2 authorization page
2. User authenticates at Hub
3. Hub redirects back to `/login/hub/callback` with an authorization code
4. Package exchanges the code for a token and fetches the user from Hub's `/api/me`
5. Local user is resolved (created, linked, or retrieved — see below)
6. User is logged into the Laravel session and redirected to `redirect_after_login`

## User Resolution

The `DefaultHubUserResolver` handles three scenarios on every login:

- **Existing Hub user** — looks up by `hub_user_id`, returns the local user as-is
- **Email match** — links the existing local account to the Hub by setting `hub_user_id`
- **New user** — creates a local account from Hub data with a null password

### Username generation

If your User model has `username` in its `$fillable` array, a username is derived from the email prefix and made unique by appending a counter on collision (e.g., `alice`, `alice1`, `alice2`).

### Role assignment

If [Spatie Laravel-Permission](https://github.com/spatie/laravel-permission) is installed and the User model uses the `HasRoles` trait, new users are assigned the `HUB_DEFAULT_ROLE` on creation. The role must exist in the database. Role assignment is skipped (with a debug log) if the package or role is not found.

## Registered Routes

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/login/hub` | `hub.login` | Redirect to Hub OAuth |
| GET | `/login/hub/callback` | `hub.callback` | Handle Hub callback |

## Custom User Resolution

To override how Hub users are resolved to local users, bind your own implementation of `ResolvesHubUser` in a service provider:

```php
use BeviBeverage\HubAuthClient\Contracts\ResolvesHubUser;

$this->app->bind(ResolvesHubUser::class, MyCustomUserResolver::class);
```

Your class must implement:

```php
public function resolve(SocialiteUser $hubUser): Authenticatable;
```

## Running Tests

```bash
composer test
```

## License

MIT
