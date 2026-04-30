<?php

namespace Lemoba\MobileMonetization;

use Illuminate\Support\ServiceProvider;
use Lemoba\MobileMonetization\Ads\LevelPlayService;
use Lemoba\MobileMonetization\Auth\AppleLoginVerifier;
use Lemoba\MobileMonetization\Auth\GoogleLoginVerifier;
use Lemoba\MobileMonetization\Payments\AppleIapVerifier;
use Lemoba\MobileMonetization\Payments\GooglePlayVerifier;
use Lemoba\MobileMonetization\Push\FirebaseCloudMessaging;

class MobileMonetizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mobile-monetization.php', 'mobile-monetization');
        $this->mergeConfigFrom(__DIR__ . '/../config/mobile-auth.php', 'mobile-auth');
        $this->mergeConfigFrom(__DIR__ . '/../config/mobile-payments.php', 'mobile-payments');
        $this->mergeConfigFrom(__DIR__ . '/../config/mobile-ads.php', 'mobile-ads');
        $this->mergeConfigFrom(__DIR__ . '/../config/mobile-push.php', 'mobile-push');

        $this->app->singleton(AppleLoginVerifier::class, fn ($app) => new AppleLoginVerifier(
            $app['config']['mobile-auth.apple'],
            null,
            $app['config']['mobile-monetization.cache']
        ));
        $this->app->singleton(GoogleLoginVerifier::class, fn ($app) => new GoogleLoginVerifier(
            $app['config']['mobile-auth.google'],
            null,
            $app['config']['mobile-monetization.cache']
        ));
        $this->app->singleton(AppleIapVerifier::class, fn ($app) => new AppleIapVerifier(
            $app['config']['mobile-payments.apple'],
            $app['config']['mobile-monetization.cache']
        ));
        $this->app->singleton(GooglePlayVerifier::class, fn ($app) => new GooglePlayVerifier(
            $app['config']['mobile-payments.google'],
            $app['config']['mobile-monetization.cache']
        ));
        $this->app->singleton(LevelPlayService::class, fn ($app) => new LevelPlayService($app['config']['mobile-ads.levelplay']));
        $this->app->singleton(FirebaseCloudMessaging::class, fn ($app) => new FirebaseCloudMessaging(
            $app['config']['mobile-push.fcm'],
            $app['config']['mobile-monetization.cache']
        ));
        $this->app->singleton(MobileMonetizationManager::class);
        $this->app->alias(MobileMonetizationManager::class, 'mobile-monetization');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mobile-monetization.php' => config_path('mobile-monetization.php'),
                __DIR__ . '/../config/mobile-auth.php' => config_path('mobile-auth.php'),
                __DIR__ . '/../config/mobile-payments.php' => config_path('mobile-payments.php'),
                __DIR__ . '/../config/mobile-ads.php' => config_path('mobile-ads.php'),
                __DIR__ . '/../config/mobile-push.php' => config_path('mobile-push.php'),
            ], 'mobile-monetization-config');
        }
    }
}
