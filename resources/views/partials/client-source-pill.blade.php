@php
    $source = $clientSource ?? ($client_source ?? 'online');
    $label = $clientSourceLabel ?? ($client_source_label ?? 'Online Appointment');
@endphp
<span class="client-source-pill client-source-pill--{{ $source }}" title="Client source">{{ $label }}</span>
