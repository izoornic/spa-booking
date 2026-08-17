@props([
    'trajne' => 0,
    'uslovne' => 0,
    'kapacitet' => 0,
])

@php
    $slobodno = max(0, (int) $kapacitet - (int) $trajne - (int) $uslovne);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-[2px]']) }} role="img"
    aria-label="{{ $trajne }} {{ __('trajnih') }}, {{ $uslovne }} {{ __('uslovnih') }}, {{ $slobodno }} {{ __('slobodnih mesta') }}">
    @for ($i = 1; $i <= (int) $kapacitet; $i++)
        @if ($i <= (int) $trajne)
            <x-spa.swatch tip="trajna" />
        @elseif ($i <= (int) $trajne + (int) $uslovne)
            <x-spa.swatch tip="uslovna" />
        @else
            <x-spa.swatch tip="slobodno" />
        @endif
    @endfor
</div>
