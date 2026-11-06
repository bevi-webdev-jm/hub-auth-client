<?php

namespace BeviWebdevJm\HubAuthClient\Tests\Feature;

use BeviWebdevJm\HubAuthClient\HubActivityReporter;
use BeviWebdevJm\HubAuthClient\Tests\Stubs\User;
use BeviWebdevJm\HubAuthClient\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class ReportHubActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Route::middleware('web')->get('/dashboard', fn () => 'ok');
    }

    private function fakeHub(int $activityStatus = 200): void
    {
        Http::fake([
            'http://hub.test/oauth/token' => Http::response(['access_token' => 'app-token', 'expires_in' => 3600]),
            'http://hub.test/api/hub/activity' => Http::response(['recorded' => 1], $activityStatus),
        ]);
    }

    private function person(): User
    {
        return User::create(['name' => 'Jane Doe', 'email' => 'Jane@Example.com']);
    }

    public function test_a_signed_in_request_reports_the_person_to_the_hub_with_the_apps_token(): void
    {
        $this->fakeHub();

        $this->actingAs($this->person())->get('/dashboard')->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://hub.test/oauth/token'
            && $request['grant_type'] === 'client_credentials'
            && $request['client_id'] === 'test-client-id');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://hub.test/api/hub/activity'
            && $request->hasHeader('Authorization', 'Bearer app-token')
            && $request['people'][0]['email'] === 'jane@example.com');
    }

    public function test_a_person_is_reported_once_a_minute_and_the_token_is_kept(): void
    {
        $this->fakeHub();
        $person = $this->person();

        $this->actingAs($person)->get('/dashboard');
        $this->actingAs($person)->get('/dashboard');

        Http::assertSentCount(2);

        $this->travel(61)->seconds();
        $this->actingAs($person)->get('/dashboard');

        Http::assertSentCount(3);
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => $pair[0]->url() === 'http://hub.test/oauth/token')->count());
    }

    public function test_guests_and_switched_off_reporting_send_nothing(): void
    {
        $this->fakeHub();

        $this->get('/dashboard')->assertOk();

        $this->app['config']->set('hub-auth.report_activity', false);
        $this->assertFalse($this->app->make(HubActivityReporter::class)->report($this->person()));

        Http::assertNothingSent();
    }

    public function test_a_refused_token_is_dropped_and_a_hub_that_is_down_does_not_break_the_page(): void
    {
        $this->fakeHub(401);
        $person = $this->person();

        $this->actingAs($person)->get('/dashboard')->assertOk();
        $this->assertNull(Cache::get(HubActivityReporter::TOKEN_KEY));

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->travel(61)->seconds();

        $this->actingAs($person)->get('/dashboard')->assertOk();
    }
}
