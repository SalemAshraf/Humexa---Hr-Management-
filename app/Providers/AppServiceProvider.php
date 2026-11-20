<?php

namespace App\Providers;

use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Policies\EmployeePolicy;
use App\Modules\Users\Providers\PlatformUserProvider;
use App\Modules\Users\Providers\TenantUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Auth::provider('tenant-scoped', function ($app, array $config) {
            return new TenantUserProvider(
                $app['hash']->driver(),
                $config['model']
            );
        });

        Auth::provider('platform-scoped', function ($app, array $config) {
            return new PlatformUserProvider(
                $app['hash']->driver(),
                $config['model']
            );
        });

        Gate::policy(Employee::class, EmployeePolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
