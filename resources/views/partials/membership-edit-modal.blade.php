@php($editingMembership = $errors->getBag('membership')->any() && (int) old('_membership_plan_id') === $plan->id)
<div class="profile-modal hidden-section membership-modal" id="membership-modal-{{ $plan->id }}" role="dialog" aria-modal="true" aria-labelledby="membership-modal-title-{{ $plan->id }}" aria-hidden="true" data-return-focus="#membership-edit-trigger-{{ $plan->id }}" @if ($editingMembership) data-membership-errors @endif>
    <div class="profile-modal-backdrop" data-close-membership-modal></div>
    <div class="profile-modal-content service-modal-panel membership-modal-panel">
        <button class="profile-modal-close service-modal-close" type="button" data-close-membership-modal aria-label="Close membership editor">&times;</button>
        <div class="membership-modal-body">
            <header class="service-modal-header">
                <div class="service-modal-icon" aria-hidden="true"><i class="bi bi-pencil-square"></i></div>
                <div class="service-modal-header-copy">
                    <h2 class="profile-modal-title" id="membership-modal-title-{{ $plan->id }}">Edit membership</h2>
                    <p class="service-modal-subtitle">Update membership details, pricing, validity, and availability.</p>
                </div>
            </header>
            <form class="service-modal-form membership-modal-form" method="POST" action="{{ route('membership-plans.update', $plan) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_membership_plan_id" value="{{ $plan->id }}">
                <p class="services-field-hint">Changes apply to the advertised plan and future purchases. Existing purchases keep their price and validity.</p>
                @if ($editingMembership)
                    <ul class="services-error-list" role="alert">
                        @foreach ($errors->getBag('membership')->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                @endif
                <div class="service-form-flow">
                    <section class="service-form-section">
                        <h3 class="service-form-section-title">Basic information</h3>
                        <div class="profile-field">
                            <label for="membership-name-{{ $plan->id }}">Membership name</label>
                            <input id="membership-name-{{ $plan->id }}" name="name" value="{{ $editingMembership ? old('name') : $plan->name }}" maxlength="255" required>
                        </div>
                        <div class="profile-field">
                            <label for="membership-description-{{ $plan->id }}">Description</label>
                            <textarea id="membership-description-{{ $plan->id }}" name="description" rows="3" maxlength="5000" required>{{ $editingMembership ? old('description') : $plan->description }}</textarea>
                        </div>
                    </section>
                    <section class="service-form-section">
                        <h3 class="service-form-section-title">Pricing &amp; validity</h3>
                        <div class="profile-field two-col">
                            <div>
                                <label for="membership-price-{{ $plan->id }}">Price (PHP)</label>
                                <input id="membership-price-{{ $plan->id }}" type="number" name="price_amount" value="{{ $editingMembership ? old('price_amount') : $plan->price_amount }}" min="1" max="99999999.99" step="0.01" required>
                            </div>
                            <div>
                                <label for="membership-validity-{{ $plan->id }}">Validity (days)</label>
                                <input id="membership-validity-{{ $plan->id }}" type="number" name="validity_days" value="{{ $editingMembership ? old('validity_days') : $plan->validity_days }}" min="1" max="3650" step="1" required>
                            </div>
                        </div>
                    </section>
                    <section class="service-form-section">
                        <h3 class="service-form-section-title">Benefits &amp; availability</h3>
                        <div class="profile-field">
                            <label for="membership-benefits-{{ $plan->id }}">Benefits (one per line)</label>
                            <textarea id="membership-benefits-{{ $plan->id }}" name="benefits" rows="4" maxlength="5000">{{ $editingMembership ? old('benefits') : implode("\n", $plan->benefits ?? []) }}</textarea>
                            <span class="services-field-hint">Describes the benefits shown to customers. Package member rates are edited in Packages.</span>
                        </div>
                        <div class="profile-field">
                            <label for="membership-active-{{ $plan->id }}">Availability</label>
                            <select id="membership-active-{{ $plan->id }}" name="is_active" required>
                                <option value="1" @selected((string) ($editingMembership ? old('is_active') : (int) $plan->is_active) === '1')>Available for new purchases</option>
                                <option value="0" @selected((string) ($editingMembership ? old('is_active') : (int) $plan->is_active) === '0')>Unavailable for new purchases</option>
                            </select>
                        </div>
                    </section>
                </div>
                <div class="service-modal-actions">
                <div class="service-modal-actions-right">
                    <button type="button" class="user-action" data-close-membership-modal>Cancel</button>
                    <button type="submit" class="user-action add">Save membership</button>
                </div>
            </div>
            </form>
        </div>
    </div>
</div>
