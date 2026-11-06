<?php

namespace BeviWebdevJm\HubAuthClient\Http;

use BeviWebdevJm\HubAuthClient\HubActivityReporter;
use Closure;
use Illuminate\Http\Request;

/**
 * Tells the Hub that the signed-in person is using this app, so the Hub's
 * online list can show them as "Online in <this app>". It runs after the
 * response has been sent and reports each person at most once a minute.
 */
class ReportHubActivity
{
    public function __construct(private HubActivityReporter $reporter)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        $user = $request->user();

        if ($user !== null) {
            $this->reporter->report($user);
        }
    }
}
