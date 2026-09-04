<x-layout title="Sign in — CodeBlue Register">
    <div class="min-h-[70vh] flex items-center justify-center">
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8 text-center">
            <h1 class="text-2xl font-semibold text-slate-900 mb-1">CodeBlue Register</h1>
            <p class="text-slate-500 mb-8">Sign in with your CodeBlue Microsoft 365 account to start checking out customers.</p>

            @if (session('error'))
                <div class="mb-6 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3">
                    {{ session('error') }}
                </div>
            @endif

            <a href="{{ route('auth.azure.redirect') }}"
                class="flex items-center justify-center gap-3 w-full min-h-[52px] rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-medium text-base">
                <svg width="20" height="20" viewBox="0 0 21 21" fill="none" aria-hidden="true">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                Sign in with Microsoft
            </a>
        </div>
    </div>
</x-layout>
