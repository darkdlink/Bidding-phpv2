@props(['type' => 'primary', 'pill' => false])

@php
    $pillClass = $pill ? 'rounded-pill' : '';
@endphp

<span {{ $attributes->merge(['class' => "badge bg-$type $pillClass"]) }}>
    {{ $slot }}
</span>
