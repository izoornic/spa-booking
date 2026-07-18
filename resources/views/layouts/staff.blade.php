<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-emerald-50 antialiased dark:bg-zinc-950">
        <header class="sticky top-0 z-10 border-b border-emerald-200 bg-emerald-600 text-white shadow-sm dark:border-emerald-900 dark:bg-emerald-800">
            <div class="mx-auto flex w-full max-w-md items-center justify-between gap-3 px-4 py-3">
                <a href="{{ route('domar.home') }}" wire:navigate class="flex items-center gap-2 font-semibold">
                    <flux:icon.building-office-2 class="size-5" />
                    {{ __('Domar · Spa') }}
                </a>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-emerald-100">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-emerald-100 underline underline-offset-2 hover:text-white">
                            {{ __('Odjava') }}
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-md flex-col gap-6 p-4">
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
