@auth
    @php
        $u = auth()->user();
        $receptionistProfile = null;
        if ($u->isReceptionist()) {
            $receptionistProfile = \App\Models\Receptionist::query()
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $u->email)])
                ->orWhereRaw('LOWER(username) = ?', [strtolower((string) $u->username)])
                ->first();
        }
        $label = $u->name ?? $u->email;
        $profilePhotoUrl = public_storage_url($u->profile_photo_path);
        $src = trim((string) $label);
        $w = preg_split('/\s+/', $src) ?: [];
        $initial = '';
        if (count($w) >= 2) {
            $last = $w[count($w) - 1] ?? '';
            $initial = strtoupper(substr($w[0], 0, 1).substr((string) $last, 0, 1));
        } else {
            $initial = strtoupper(substr($src, 0, 2));
        }
        $initial = $initial !== '' ? $initial : 'U';
        $receptionistBirthdayValue = old('receptionist_birthday');
        if ($receptionistBirthdayValue === null && $receptionistProfile?->birthday) {
            $receptionistBirthdayValue = $receptionistProfile->birthday->format('Y-m-d');
        }
        $receptionistAge = $receptionistProfile?->birthday?->age;
        $showPasswordForm =
            $errors->profile->has('current_password')
            || $errors->profile->has('password')
            || $errors->profile->has('password_confirmation');
    @endphp

    <div class="mp-modal mp-hidden" id="tnr-my-profile-modal" role="dialog" aria-modal="true" aria-labelledby="mp-modal-title">
        <div class="mp-modal-backdrop" data-mp-close="true"></div>
        <div class="mp-dialog" role="document">
            <div class="mp-header">
                <h2 class="mp-title" id="mp-modal-title">My Profile</h2>
                <button type="button" class="mp-close" data-mp-close="true" aria-label="Close">&times;</button>
            </div>

            @if ($errors->profile->any())
                <div class="mp-errors" role="alert">
                    <ul>
                        @foreach ($errors->profile->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mp-body">
                <div class="mp-panel" id="mp-panel-edit">
                    <form id="mp-profile-photo-form" class="mp-avatar-block" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
                        @csrf
                            <div class="mp-avatar-wrap">
                                <div class="mp-avatar-lg" aria-hidden="true">
                                    @if ($profilePhotoUrl)
                                        <img
                                            id="mp-profile-avatar-preview"
                                            src="{{ $profilePhotoUrl }}"
                                            alt="{{ $label }} profile photo"
                                            onerror="this.classList.add('mp-hidden-preview'); document.getElementById('mp-profile-avatar-fallback')?.removeAttribute('style');"
                                        >
                                    @else
                                        <span id="mp-profile-avatar-fallback">{{ $initial }}</span>
                                        <img id="mp-profile-avatar-preview" src="" alt="{{ $label }} profile photo" class="mp-hidden-preview">
                                    @endif
                                </div>
                                <input id="mp-profile-photo-input" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="mp-hidden-input">
                                <button type="button" class="mp-avatar-edit" aria-label="Change profile photo" title="Change profile photo">
                                    <i class="bi bi-pencil-fill" aria-hidden="true"></i>
                                </button>
                            </div>
                            <button type="submit" class="mp-photo-save">Save photo</button>
                    </form>

                    <form id="mp-profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mp-grid">
                            <div class="mp-field">
                                <label for="mp-name">Name</label>
                                <input id="mp-name" name="name" type="text" value="{{ old('name', $u->name) }}" required autocomplete="name">
                            </div>
                            <div class="mp-field">
                                <label for="mp-email">E-mail</label>
                                <input id="mp-email" name="email" type="email" value="{{ old('email', $u->email) }}" required autocomplete="email">
                            </div>
                            <div class="mp-field">
                                <label for="mp-phone">Phone Number</label>
                                <input id="mp-phone" name="contact_number" type="text" value="{{ old('contact_number', $u->contact_number) }}" autocomplete="tel" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric" placeholder="e.g. 09171234567">
                            </div>
                            <div class="mp-field">
                                <label for="mp-username">Username</label>
                                <input id="mp-username" name="username" type="text" value="{{ old('username', $u->username) }}" required autocomplete="username" placeholder="e.g. jane_doe" minlength="3" maxlength="30">
                            </div>
                            @if ($u->isReceptionist())
                                <div class="mp-field mp-field-span2">
                                    <label for="mp-receptionist-address">Address</label>
                                    <input
                                        id="mp-receptionist-address"
                                        name="receptionist_address"
                                        type="text"
                                        value="{{ old('receptionist_address', $receptionistProfile?->address) }}"
                                        autocomplete="street-address"
                                        placeholder="Enter address"
                                    >
                                </div>
                                <div class="mp-field">
                                    <label for="mp-receptionist-birthday">Birthday</label>
                                    <input
                                        id="mp-receptionist-birthday"
                                        name="receptionist_birthday"
                                        type="date"
                                        value="{{ $receptionistBirthdayValue }}"
                                        max="{{ now()->toDateString() }}"
                                    >
                                </div>
                                <div class="mp-field">
                                    <label for="mp-receptionist-age">Age</label>
                                    <input
                                        id="mp-receptionist-age"
                                        type="text"
                                        value="{{ $receptionistAge !== null ? $receptionistAge : '' }}"
                                        readonly
                                        tabindex="-1"
                                        placeholder="Auto-calculated"
                                    >
                                </div>
                            @endif
                        </div>

                        <div class="mp-change-password-row">
                            <button
                                type="button"
                                class="mp-change-password-btn"
                                id="mp-change-password-toggle"
                                aria-expanded="{{ $showPasswordForm ? 'true' : 'false' }}"
                                aria-controls="mp-password-block"
                            >
                                Change password
                            </button>
                        </div>

                        <div class="mp-password-block {{ $showPasswordForm ? 'is-open' : '' }}" id="mp-password-block">
                            <div class="mp-password-panel">
                                <h3 class="mp-password-panel-title">Change password</h3>
                                <div class="mp-grid mp-password-fields">
                                    <div class="mp-field mp-field-span2">
                                        <label for="mp-current-password">Current password</label>
                                        <input id="mp-current-password" name="current_password" type="password" autocomplete="current-password">
                                    </div>
                                    <div class="mp-field">
                                        <label for="mp-new-password">New password</label>
                                        <input id="mp-new-password" name="password" type="password" autocomplete="new-password">
                                    </div>
                                    <div class="mp-field">
                                        <label for="mp-new-password-confirmation">Confirm new password</label>
                                        <input id="mp-new-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mp-footer mp-footer-save-only">
                <button type="submit" class="mp-save" form="mp-profile-form">Save</button>
            </div>
        </div>
    </div>
@endauth
