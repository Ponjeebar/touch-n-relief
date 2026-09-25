<div class="auth-legal-modal" data-auth-legal-modal hidden aria-hidden="true">
    <div class="auth-legal-modal-backdrop" data-auth-legal-close></div>
    <section class="auth-legal-modal-panel" role="dialog" aria-modal="true" aria-labelledby="auth-legal-modal-title">
        <header class="auth-legal-modal-header">
            <div>
                <span data-auth-legal-eyebrow></span>
                <h2 id="auth-legal-modal-title" data-auth-legal-title></h2>
            </div>
            <button type="button" class="auth-legal-modal-close" data-auth-legal-close aria-label="Close legal information">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>
        <div class="auth-legal-modal-body">
            @foreach (['privacy', 'terms'] as $documentKey)
                @php($document = config('legal.'.$documentKey))
                <article data-auth-legal-document="{{ $documentKey }}" hidden>
                    <p class="auth-legal-modal-intro">{{ $document['intro'] }}</p>
                    <small>Effective September 25, 2026</small>
                    <div class="auth-legal-modal-sections">
                        @foreach ($document['sections'] as [$heading, $body])
                            <section>
                                <h3>{{ $heading }}</h3>
                                <p>{{ $body }}</p>
                            </section>
                        @endforeach
                    </div>
                </article>
            @endforeach
        </div>
        <footer class="auth-legal-modal-footer">
            <button type="button" data-auth-legal-close>Close</button>
        </footer>
    </section>
</div>
