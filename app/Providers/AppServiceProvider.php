<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\FlutterwaveGateway;
use App\Payments\MobileMoneyGateway;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mobile Money provider: "flutterwave" in production/sandbox, "fake" for demos and local work.
        $this->app->bind(MobileMoneyGateway::class, function () {
            return match (config('services.mobile_money.driver')) {
                'flutterwave' => new FlutterwaveGateway(
                    (string) config('services.flutterwave.secret_key'),
                    (string) config('services.flutterwave.base_url'),
                    (int) config('services.flutterwave.timeout', 30),
                ),
                default => new FakeGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
