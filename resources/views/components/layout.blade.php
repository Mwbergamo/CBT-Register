<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CodeBlue Register' }}</title>

    {{-- Tailwind via CDN: deliberately no Node/npm build step, so this app
         has zero build tooling requirement on Bluehost shared hosting. See
         docs/BLUEHOST_DEPLOYMENT.md. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { brand: { 600: '#1d4ed8', 700: '#1e40af' } },
                    fontSize: { base: '1.05rem' },
                },
            },
        };
    </script>
    <style>
        /* Larger, thumb-friendly hit targets everywhere by default. */
        button, [role="button"], a.btn, input, select { touch-action: manipulation; }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
    </style>

    {{-- Tailwind Play CDN supports @apply inside a text/tailwindcss block —
         used here for one shared input style so form fields stay consistent
         without a build step. --}}
    <style type="text/tailwindcss">
        .cw-input {
            @apply w-full min-h-[48px] rounded-lg border-slate-300 focus:border-brand-600 focus:ring-brand-600 text-base;
        }
    </style>

    @livewireStyles
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <div class="min-h-full flex flex-col">
        <header class="bg-brand-700 text-white shadow">
            <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-lg sm:text-xl font-semibold tracking-tight">CodeBlue Register</span>
                </div>
                @auth
                    <div class="flex items-center gap-4">
                        <span class="hidden sm:inline text-sm text-blue-100">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="min-h-[44px] px-4 rounded-lg bg-brand-600 hover:bg-blue-800 text-sm font-medium">
                                Sign out
                            </button>
                        </form>
                    </div>
                @endauth
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-6xl px-3 sm:px-4 py-4 sm:py-6">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
