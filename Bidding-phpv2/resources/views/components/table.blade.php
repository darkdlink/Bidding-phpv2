@props(['headers' => [], 'striped' => true, 'hover' => true, 'responsive' => true])

@php
    $tableClass = 'table';
    if ($striped) $tableClass .= ' table-striped';
    if ($hover) $tableClass .= ' table-hover';
@endphp

@if($responsive)
<div class="table-responsive">
@endif

    <table {{ $attributes->merge(['class' => $tableClass]) }}>
        @if(count($headers) > 0)
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>
    </table>

@if($responsive)
</div>
@endif
