<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/**
 * Microsoft 365 / Entra ID SSO. There is no local password login — every
 * associate signs in with their CodeBlue Microsoft 365 account, and access
 * is ultimately controlled in Azure AD (who the app is assigned to /
 * conditional access), not by anything stored in this app. See
 * docs/AZURE_AD_SETUP.md.
 */
class AzureLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        // config('services.azure.tenant') is what actually restricts sign-in
        // to CodeBlue's own Microsoft 365 tenant — set it to your tenant ID,
        // never "common"/"organizations" (see docs/AZURE_AD_SETUP.md).
        $azureUser = Socialite::driver('azure')->user();

        $user = User::updateOrCreate(
            ['azure_oid' => $azureUser->getId()],
            [
                'name' => $azureUser->getName() ?: $azureUser->getNickname(),
                'email' => $azureUser->getEmail(),
                'azure_upn' => $azureUser->getEmail(),
                'last_login_at' => now(),
            ]
        );

        if (! $user->is_active) {
            abort(403, 'Your account has been deactivated. Contact your manager.');
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('register.index'));
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
