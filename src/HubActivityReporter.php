<?php

namespace BeviWebdevJm\HubAuthClient;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reports to the Hub which people are using this app, with the app's own
 * client credentials (a client_credentials token, kept until it expires).
 *
 * Each person is reported at most once every `activity_every` seconds. A
 * Hub that cannot be reached is skipped quietly: the next report, a minute
 * later, tries again. The person is matched on the Hub by email.
 */
class HubActivityReporter
{
    public const TOKEN_KEY = 'hub-auth.activity-token';

    public function report($user): bool
    {
        $email = $this->emailOf($user);

        if (! $this->enabled() || $email === null) {
            return false;
        }

        $every = max(30, (int) config('hub-auth.activity_every', 60));

        if (! Cache::add('hub-auth.activity.'.sha1($email), true, $every)) {
            return false;
        }

        try {
            return $this->send([['email' => $email, 'at' => now()->toIso8601String()]]);
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function enabled(): bool
    {
        return (bool) config('hub-auth.report_activity', true)
            && filled(config('hub-auth.client_id'))
            && filled(config('hub-auth.client_secret'));
    }

    /**
     * @param  array<int, array{email: string, at: string}>  $people
     */
    private function send(array $people): bool
    {
        $token = $this->token();

        if ($token === null) {
            return false;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(3)
            ->post($this->hubUrl('/api/hub/activity'), ['people' => $people]);

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_KEY);
        }

        return $response->successful();
    }

    private function token(): ?string
    {
        $kept = Cache::get(self::TOKEN_KEY);

        if (is_string($kept) && $kept !== '') {
            return $kept;
        }

        $response = Http::asForm()->acceptJson()->timeout(3)->post($this->hubUrl('/oauth/token'), [
            'grant_type' => 'client_credentials',
            'client_id' => config('hub-auth.client_id'),
            'client_secret' => config('hub-auth.client_secret'),
            'scope' => '',
        ]);

        $token = $response->successful() ? $response->json('access_token') : null;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $seconds = max(60, (int) $response->json('expires_in', 3600) - 60);
        Cache::put(self::TOKEN_KEY, $token, $seconds);

        return $token;
    }

    private function emailOf($user): ?string
    {
        $email = is_object($user) ? ($user->email ?? null) : null;

        return is_string($email) && $email !== '' ? strtolower(trim($email)) : null;
    }

    private function hubUrl(string $path): string
    {
        return rtrim((string) config('hub-auth.hub_url'), '/').$path;
    }
}
