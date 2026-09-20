@php
    $u = auth()->user();
    if (! $u?->needsProfileOnboarding()) {
        return;
    }
    $showPregnancyStep = $u->sex === \App\Models\User::SEX_FEMALE;
    $onboardingStepCount = $showPregnancyStep ? 4 : 3;
@endphp
@once
    <link rel="stylesheet" href="{{ asset('css/registration-onboarding.css') }}">
@endonce
<div class="ob-modal" id="tnr-registration-onboarding-modal" role="dialog" aria-modal="true" aria-labelledby="ob-modal-title">
    <div class="ob-modal-backdrop" aria-hidden="true"></div>
    <div class="ob-dialog" role="document">
        <div class="ob-header">
            <h2 class="ob-title" id="ob-modal-title">Complete your wellness profile</h2>
            <p class="ob-subtitle">Help us personalize your massage experience. This only takes a minute.</p>
        </div>

        <div class="ob-progress" aria-hidden="true">
            @for ($i = 0; $i < $onboardingStepCount; $i++)
                <span class="ob-progress-dot" data-ob-dot></span>
            @endfor
        </div>

        @if ($errors->onboarding->any())
            <div class="ob-errors" role="alert">
                <ul>
                    @foreach ($errors->onboarding->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="tnr-onboarding-form" method="POST" action="{{ route('onboarding.store') }}" novalidate>
            @csrf
            <input type="hidden" name="return_to" value="{{ url()->full() }}">
            <div class="ob-body">
                <div class="ob-step is-active" data-ob-step>
                    <h3 class="ob-step-title">Current medications</h3>
                    <p class="ob-step-hint">List any medications you are taking (optional). You can skip if none.</p>
                    <div class="ob-med-editor" id="ob-med-editor">
                        <div class="ob-med-row">
                            <input type="text" name="medications[]" value="" placeholder="e.g. Ibuprofen (Advil)" maxlength="255">
                            <button type="button" class="ob-med-remove" data-ob-med-remove aria-label="Remove">&times;</button>
                        </div>
                    </div>
                    <button type="button" class="ob-med-add" id="ob-med-add">+ Add medication</button>
                </div>

                <div class="ob-step" data-ob-step>
                    <h3 class="ob-step-title">Therapist gender preference</h3>
                    <p class="ob-step-hint">Do you have a therapist gender preference?</p>
                    <div class="ob-options">
                        <input class="ob-hidden-input" type="radio" name="therapist_gender_preference" value="male" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="therapist_gender_preference" data-ob-value="male">Male therapist</button>
                        <input class="ob-hidden-input" type="radio" name="therapist_gender_preference" value="female" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="therapist_gender_preference" data-ob-value="female">Female therapist</button>
                        <input class="ob-hidden-input" type="radio" name="therapist_gender_preference" value="no_preference" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="therapist_gender_preference" data-ob-value="no_preference">No preference</button>
                    </div>
                </div>

                @if ($showPregnancyStep)
                    <div class="ob-step" data-ob-step data-ob-step-pregnancy>
                        <h3 class="ob-step-title">Pregnancy</h3>
                        <p class="ob-step-hint">Are you currently pregnant?</p>
                        <div class="ob-options">
                            <input class="ob-hidden-input" type="radio" name="is_pregnant" value="1" data-ob-required>
                            <button type="button" class="ob-option" data-ob-option="is_pregnant" data-ob-value="1">Yes</button>
                            <input class="ob-hidden-input" type="radio" name="is_pregnant" value="0" data-ob-required>
                            <button type="button" class="ob-option" data-ob-option="is_pregnant" data-ob-value="0">No</button>
                        </div>
                    </div>
                @endif

                <div class="ob-step" data-ob-step>
                    <h3 class="ob-step-title">Preferred pressure level</h3>
                    <p class="ob-step-hint">What is your preferred pressure level during massage?</p>
                    <div class="ob-options">
                        <input class="ob-hidden-input" type="radio" name="pressure_preference" value="low" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="pressure_preference" data-ob-value="low">Low pressure</button>
                        <input class="ob-hidden-input" type="radio" name="pressure_preference" value="medium" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="pressure_preference" data-ob-value="medium">Medium pressure</button>
                        <input class="ob-hidden-input" type="radio" name="pressure_preference" value="high" data-ob-required>
                        <button type="button" class="ob-option" data-ob-option="pressure_preference" data-ob-value="high">High pressure</button>
                    </div>
                </div>
            </div>

            <div class="ob-footer">
                <button type="button" class="ob-btn ob-btn-secondary" id="ob-back-btn" disabled>Back</button>
                <button type="button" class="ob-btn ob-btn-primary" id="ob-next-btn">Next</button>
            </div>
        </form>
    </div>
</div>
<script src="{{ asset('js/registration-onboarding.js') }}"></script>
