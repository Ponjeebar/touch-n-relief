<span class="live-clock-wrap">
    <i class="bi bi-clock" aria-hidden="true"></i>
    <span data-live-clock="true">{{ now()->format('D, M j, Y g:i:s A') }}</span>
    <span class="live-clock-zone">{{ $appTimezone ?? config('app.timezone') }}</span>
</span>
