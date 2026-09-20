@php
    use App\Support\TherapistSpecialtyDisplay;

    $tagDisplay = TherapistSpecialtyDisplay::forCard($specialties ?? [], $maxVisible ?? 2);
@endphp
@foreach ($tagDisplay['visible'] as $tag)
    <span class="{{ $tagClass ?? 'therapist-tag' }}" title="{{ $tag }}">{{ $tag }}</span>
@endforeach
@if ($tagDisplay['overflow'] > 0)
    <span class="{{ trim(($tagClass ?? 'therapist-tag').' '.($overflowClass ?? '').' therapist-tag-more') }}" title="+{{ $tagDisplay['overflow'] }} more">+{{ $tagDisplay['overflow'] }}</span>
@endif
