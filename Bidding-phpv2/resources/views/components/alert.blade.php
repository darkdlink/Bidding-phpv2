@props(['type' => 'info', 'dismissible' => true])

@php
    $typeClass = [
        'success' => 'alert-success',
        'info' => 'alert-info',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger',
        'primary' => 'alert-primary',
        'secondary' => 'alert-secondary',
        'light' => 'alert-light',
        'dark' => 'alert-dark',
    ][$type] ?? 'alert-info';
@endphp

<div {{ $attributes->merge(['class' => "alert $typeClass"]) }} role="alert">
    @if ($dismissible)
        <button type="button" class="btn-close float-end" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif

    {{ $slot }}
</div>
