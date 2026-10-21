<?php

namespace Tests\Feature\Security;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Structural checks that catch a new route or admin screen added without protection.
 */
class SecuritySweepTest extends TestCase
{
    /** API writes that are deliberately public (signing in is impossible without them). */
    private const PUBLIC_WRITES = [
        'api/v1/auth/register',
        'api/v1/auth/login',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
    ];

    public function test_every_api_write_requires_a_signed_in_active_account(): void
    {
        $checked = 0;
        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), self::PUBLIC_WRITES, true)) {
                continue;
            }
            if (array_diff($route->methods(), ['GET', 'HEAD']) === []) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "{$route->uri()} must require sign-in.");
            $this->assertContains('account.active', $middleware, "{$route->uri()} must refuse suspended accounts.");
            $this->assertTrue(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')), "{$route->uri()} must be rate limited.");
            $checked++;
        }
        $this->assertGreaterThan(30, $checked);
    }

    public function test_private_api_reads_require_sign_in(): void
    {
        foreach (RouteFacade::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/me') || $route->uri() === 'api/v1/users') {
                $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "{$route->uri()} returns private data.");
            }
        }
    }

    public function test_every_admin_resource_has_a_policy_and_every_custom_page_checks_access(): void
    {
        $panel = Filament::getPanel('admin');
        foreach ($panel->getResources() as $resource) {
            $this->assertNotNull(Gate::getPolicyFor($resource::getModel()), "{$resource} has no policy: authorization would fall back to allowing every staff member.");
        }
        foreach ($panel->getPages() as $page) {
            if ($page === Dashboard::class) {
                continue;
            }
            $declaring = (new ReflectionMethod($page, 'canAccess'))->getDeclaringClass()->getName();
            $this->assertSame($page, $declaring, "{$page} must declare canAccess().");
        }
    }

    public function test_api_errors_do_not_leak_internals(): void
    {
        config(['app.debug' => false]); // as in production (rivalo:launch-check fails when it is on)
        $this->getJson('/api/v1/tournaments/does-not-exist')->assertNotFound()->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
        $this->getJson('/api/v1/users/nobody_here')->assertNotFound()->assertJsonMissingPath('file');
    }
}
