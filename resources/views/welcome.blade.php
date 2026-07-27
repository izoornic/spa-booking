<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        <!-- Styles -->
        @vite(['resources/css/app.css'])

        {{-- Postavi temu pre iscrtavanja da nema treptaja (FOUC). --}}
        <style>[x-cloak]{display:none!important;}</style>
        <script>
            (function () {
                const stored = localStorage.getItem('theme');
                const dark = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
    </head>
    <body
        x-data="{ dark: document.documentElement.classList.contains('dark') }"
        x-init="$watch('dark', value => {
            document.documentElement.classList.toggle('dark', value);
            localStorage.setItem('theme', value ? 'dark' : 'light');
        })"
        class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] flex p-0 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <div class="w-full lg:w-auto min-h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
                <!-- Header -->
        <header class="w-full lg:max-w-4xl text-sm mb-6 not-has-[nav]:hidden bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-10">
            <div class="px-1 py-1 flex items-center justify-between">
                <x-icon-logo-small class="w-12 h-12" />
                
                <div class="flex">
                    @if (Route::has('login'))
                        <nav class="flex items-center justify-end gap-4">
                            @auth
                                <a
                                    href="{{ auth()->user()->homeUrl() }}"
                                    class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                                >
                                    Početna
                                </a>
                            @else
                                <a
                                    href="{{ route('login') }}"
                                    class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                                >
                                    Prijava
                                </a>

                                @if (Route::has('register'))
                                    <a
                                        href="{{ route('register') }}"
                                        class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                        Register
                                    </a>
                                @endif
                            @endauth
                        </nav>
                    @endif
                    <button 
                        @click="dark = !dark"
                        type="button"
                        class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg x-show="!dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="dark" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
            <main class="w-full lg:max-w-4xl">
                <div class="w-full lg:w-1/2">
                    <img src="img/Spa_home.png" alt="Hero image" class="w-full h-full object-cover" />
                </div>
                <div class="flex flex-col items-center justify-center">
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl md:text-5xl">
                        <span class="block xl:inline">Spa booking</span>
                    </h1>
                    <p class="mt-3 text-lg text-gray-500 dark:text-gray-400 text-center">
                        {{ __('Dobrodošli') }}
                        <br />
                        {{ __('Aplikacija za rezervaciju i pregled rezervacija') }}
                    </p>

                </div>   
            </main>

            <nav class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 pb-2">
                <div class="w-full lg:max-w-4xl mx-auto px-4">
                    <div class="flex items-center justify-around">

                    </div>
                    <div class="flex justify-between m-0 py-0 max-h-[8px]">
                        <div>&nbsp;</div>
                        <div class="text-xs leading-none text-gray-700 dark:text-gray-400">{{ config('global.siteFooter') }}</div>
                        <div class="text-right text-xs leading-none text-gray-700 dark:text-gray-400">{{ config('global.version') }}</div> 
                    </div>
                </div>
            </nav>

        </div>

        
        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif

        @fluxScripts
    </body>
</html>
