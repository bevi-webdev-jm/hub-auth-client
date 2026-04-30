<?php

namespace BeviWebdevJm\HubAuthClient\Resolvers;

use BeviWebdevJm\HubAuthClient\Contracts\ResolvesHubUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\User as SocialiteUser;

class DefaultHubUserResolver implements ResolvesHubUser
{
    public function resolve(SocialiteUser $hubUser): Authenticatable
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\Authenticatable> $userModel */
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        $user = $userModel::where('hub_user_id', (string) $hubUser->getId())->first();

        if ($user) {
            return $user;
        }

        // Link an existing local account that shares the same email.
        $user = $userModel::where('email', $hubUser->getEmail())->first();

        if ($user) {
            $user->hub_user_id = (string) $hubUser->getId();
            $user->save();
            return $user;
        }

        $attributes = $this->buildCreateAttributes($hubUser, $userModel);

        $user = $userModel::create($attributes);

        $this->assignDefaultRole($user);

        return $user;
    }

    /**
     * Build the attribute array for creating a new local user.
     * Detects whether the model uses a `username` field and auto-generates one.
     *
     * @param  class-string  $userModel
     * @return array<string, mixed>
     */
    protected function buildCreateAttributes(SocialiteUser $hubUser, string $userModel): array
    {
        $attributes = [
            'hub_user_id' => (string) $hubUser->getId(),
            'name'        => $hubUser->getName(),
            'email'       => $hubUser->getEmail(),
            'password'    => null,
        ];

        $instance = new $userModel();

        if (in_array('username', $instance->getFillable())) {
            $attributes['username'] = $this->generateUniqueUsername($hubUser->getEmail() ?? '', $userModel);
        }

        return $attributes;
    }

    /**
     * Derive a unique username from the email prefix with a collision counter.
     *
     * @param  class-string  $userModel
     */
    protected function generateUniqueUsername(string $email, string $userModel): string
    {
        $base = strtolower(explode('@', $email)[0] ?? 'user');
        $username = $base;
        $counter = 1;

        while ($userModel::where('username', $username)->exists()) {
            $username = $base . $counter++;
        }

        return $username;
    }

    /**
     * Assign the configured default role if Spatie HasRoles is detected on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Model&\Illuminate\Contracts\Auth\Authenticatable  $user
     */
    protected function assignDefaultRole(mixed $user): void
    {
        $hasRolesTrait = 'Spatie\Permission\Traits\HasRoles';

        if (! in_array($hasRolesTrait, class_uses_recursive($user))) {
            Log::debug('[HubAuthClient] Spatie HasRoles not detected on User model — skipping default role assignment.');
            return;
        }

        $defaultRole = config('hub-auth.default_role', 'default_user');

        if (! \Spatie\Permission\Models\Role::where('name', $defaultRole)->exists()) {
            Log::debug("[HubAuthClient] Default role '{$defaultRole}' not found in DB — skipping assignment.");
            return;
        }

        $user->assignRole($defaultRole);
    }
}
