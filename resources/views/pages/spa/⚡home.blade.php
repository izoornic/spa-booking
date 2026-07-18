<?php

use App\Http\Middleware\EnsureOwner;
use App\Models\Vlasnik;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.owner')] #[Title('Spa rezervacije')] class extends Component {
    public Vlasnik $owner;

    public function mount(): void
    {
        $this->owner = Vlasnik::with('stan.zgrada')
            ->findOrFail(session(EnsureOwner::SESSION_KEY));
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl">{{ __('Spa rezervacije') }}</flux:heading>

    <flux:card class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Zdravo') }}, {{ $owner->ime }}!</flux:heading>
        <flux:text>
            {{ __('Stan') }} <strong>{{ $owner->stan->broj }}</strong>,
            {{ $owner->stan->zgrada->naziv }}
        </flux:text>
    </flux:card>

    <flux:callout icon="information-circle">
        {{ __('Rezervacije termina biće dostupne uskoro.') }}
    </flux:callout>
</div>
