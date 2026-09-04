<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Registers the "azure" Socialite driver used for Microsoft 365
        // SSO — see App\Http\Controllers\Auth\AzureLoginController and
        // docs/AZURE_AD_SETUP.md.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', AzureProvider::class);
        });

        // Bluehost typically terminates TLS at a load balancer/proxy in
        // front of the app, so force HTTPS URL generation in production —
        // otherwise the Azure AD redirect URI Laravel builds can come out
        // as http:// and get rejected by Entra ID.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
