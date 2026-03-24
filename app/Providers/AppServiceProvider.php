<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Blade::directive('safeColor', function (string $expression): string {
            return "<?php echo preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($expression)) === 1 ? e($expression) : '#64748b'; ?>";
        });
    }
}
