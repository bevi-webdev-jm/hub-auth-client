<?php

namespace BeviWebdevJm\HubAuthClient;

use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class HubProvider extends AbstractProvider
{
    protected string $hubUrl;

    protected $scopes = [];

    protected $scopeSeparator = ' ';

    public function __construct(Request $request, string $clientId, string $clientSecret, string $redirectUrl, string $hubUrl)
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
        $this->hubUrl = rtrim($hubUrl, '/');
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->hubUrl . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->hubUrl . '/oauth/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->hubUrl . '/api/me', [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
        ]);
    }
}
