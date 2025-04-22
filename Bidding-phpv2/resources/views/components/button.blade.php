@props(['type' => 'button', 'color' => 'primary'])

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "btn btn-$color"]) }}
>
    {{ $slot }}
</button>
