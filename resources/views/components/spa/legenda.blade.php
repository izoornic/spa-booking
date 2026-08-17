<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-500']) }}>
    <span class="flex items-center gap-1.5">
        <x-spa.swatch tip="trajna" />{{ __('Trajna') }}
    </span>
    <span class="flex items-center gap-1.5">
        <x-spa.swatch tip="uslovna" />{{ __('Uslovna') }}
    </span>
    <span class="flex items-center gap-1.5">
        <x-spa.swatch tip="slobodno" />{{ __('Slobodno') }}
    </span>
</div>
