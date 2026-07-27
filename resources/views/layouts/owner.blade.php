<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        <style>[x-cloak]{display:none!important;}</style>
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-900">
        <header class="sticky top-0 z-10 w-full border-b border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="mx-auto flex w-full max-w-md items-center justify-between px-4 py-1">
                <x-icon-logo-small class="h-12 w-12" />
                <h1 class="text-xl text-gray-900 dark:text-white">Spa booking</h1>
                <button
                    type="button"
                    x-data="{ get isDark() { return $flux.appearance === 'dark' || ($flux.appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches); } }"
                    @click="$flux.appearance = (isDark ? 'light' : 'dark')"
                    class="rounded-lg bg-gray-100 p-2 text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    <svg x-show="!isDark" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg x-show="isDark" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </button>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-md flex-col gap-6 p-4 pb-16">
            {{ $slot }}
        </main>

        <nav class="fixed bottom-0 left-0 right-0 border-t border-gray-200 bg-white pb-2 dark:border-gray-700 dark:bg-gray-800">
            <div class="mx-auto w-full max-w-md px-4">
                <div class="flex justify-between py-0 leading-none">
                    <div class="text-xs leading-none text-gray-700 dark:text-gray-400">{{ config('global.siteFooter') }}</div>
                    <div class="text-right text-xs leading-none text-gray-700 dark:text-gray-400">{{ config('global.version') }}</div>
                </div>
            </div>
        </nav>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
