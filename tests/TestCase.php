<?php

namespace BeviWebdevJm\HubAuthClient\Tests;

use BeviWebdevJm\HubAuthClient\HubAuthServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [HubAuthServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('hub-auth.hub_url', 'http://hub.test');
        $app['config']->set('hub-auth.client_id', 'test-client-id');
        $app['config']->set('hub-auth.client_secret', 'test-client-secret');
        $app['config']->set('hub-auth.redirect', 'http://localhost:8001/login/hub/callback');
        $app['config']->set('hub-auth.default_role', 'default_user');

        $app['config']->set('auth.providers.users.model', \BeviWebdevJm\HubAuthClient\Tests\Stubs\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('hub_user_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }
}
