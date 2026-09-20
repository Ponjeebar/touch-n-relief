@php
    $crShowAdd = $showAdd ?? true;
    $crAddReturnTo = $addReturnTo ?? 'users.index';
    $crEditReturnTo = $editReturnTo ?? 'users.index';
@endphp

@if ($crShowAdd)
    <div class="profile-modal hidden-section" id="cr-add-customer-modal" role="dialog" aria-modal="true" aria-labelledby="cr-add-customer-title">
        <div class="profile-modal-backdrop" data-cr-close-add-customer="true"></div>
        <div class="profile-modal-content add-modal-content">
            <button class="profile-modal-close" type="button" id="cr-close-add-customer-modal" aria-label="Close">&times;</button>
            <h3 class="profile-modal-title" id="cr-add-customer-title">Add Customer</h3>
            <form class="add-receptionist-modal-form" id="cr-add-customer-form" method="POST" action="{{ route('dashboard.customers.store') }}">
                @csrf
                <input type="hidden" name="return_to" value="{{ $crAddReturnTo }}">
                <div class="add-modal-grid">
                    <div class="profile-field">
                        <label for="cr-add-full-name">Full Name</label>
                        <input id="cr-add-full-name" type="text" name="full_name" value="{{ old('return_to') === $crAddReturnTo ? old('full_name', '') : '' }}" required>
                    </div>
                    <div class="profile-field">
                        <label for="cr-add-email">Email</label>
                        <input id="cr-add-email" type="email" name="email" value="{{ old('return_to') === $crAddReturnTo ? old('email', '') : '' }}" required>
                    </div>
                    <div class="profile-field">
                        <label for="cr-add-birthday">Birthday</label>
                        <input id="cr-add-birthday" type="date" name="birthday" value="{{ old('return_to') === $crAddReturnTo ? old('birthday', '') : '' }}">
                    </div>
                    <div class="profile-field">
                        <label for="cr-add-number">Number</label>
                        <input id="cr-add-number" type="text" name="number" value="{{ old('return_to') === $crAddReturnTo ? old('number', '') : '' }}" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric">
                    </div>
                    <div class="profile-field full-row">
                        <label for="cr-add-password">Password</label>
                        <input id="cr-add-password" type="password" name="password" required minlength="8">
                    </div>
                </div>
                <div class="profile-modal-actions">
                    <button type="button" class="user-action" id="cr-add-customer-cancel">Cancel</button>
                    <button type="submit" class="user-action add">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="profile-modal hidden-section" id="cr-edit-customer-modal" role="dialog" aria-modal="true" aria-labelledby="cr-edit-customer-title">
    <div class="profile-modal-backdrop" data-cr-close-edit-customer="true"></div>
    <div class="profile-modal-content add-modal-content">
        <button class="profile-modal-close" type="button" id="cr-close-edit-customer-modal" aria-label="Close">&times;</button>
        <h3 class="profile-modal-title" id="cr-edit-customer-title">Edit Customer</h3>
        <form class="add-receptionist-modal-form" id="cr-edit-customer-form" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_to" value="{{ $crEditReturnTo }}">
            <div class="add-modal-grid">
                <div class="profile-field">
                    <label for="cr-edit-customer-full-name">Full Name</label>
                    <input type="text" name="full_name" id="cr-edit-customer-full-name" required>
                </div>
                <div class="profile-field">
                    <label for="cr-edit-customer-email">Email</label>
                    <input type="email" name="email" id="cr-edit-customer-email" required>
                </div>
                <div class="profile-field">
                    <label for="cr-edit-customer-birthday">Birthday</label>
                    <input type="date" name="birthday" id="cr-edit-customer-birthday">
                </div>
                <div class="profile-field">
                    <label for="cr-edit-customer-number">Number</label>
                    <input type="text" name="number" id="cr-edit-customer-number" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric">
                </div>
                <div class="profile-field full-row">
                    <label for="cr-edit-customer-password">New Password (optional)</label>
                    <input type="password" name="password" id="cr-edit-customer-password" placeholder="Leave blank to keep current password">
                </div>
            </div>
            <div class="profile-modal-actions">
                <button type="button" class="user-action" id="cr-edit-customer-cancel">Cancel</button>
                <button type="submit" class="user-action edit">Update Customer</button>
            </div>
        </form>
    </div>
</div>

@once
    <script>
        (function () {
            const editModal = document.getElementById('cr-edit-customer-modal');
            const closeEdit = document.getElementById('cr-close-edit-customer-modal');
            const cancelEdit = document.getElementById('cr-edit-customer-cancel');
            const editForm = document.getElementById('cr-edit-customer-form');
            const addModal = document.getElementById('cr-add-customer-modal');
            const closeAdd = document.getElementById('cr-close-add-customer-modal');
            const cancelAdd = document.getElementById('cr-add-customer-cancel');

            const editFullName = document.getElementById('cr-edit-customer-full-name');
            const editEmail = document.getElementById('cr-edit-customer-email');
            const editBirthday = document.getElementById('cr-edit-customer-birthday');
            const editNumber = document.getElementById('cr-edit-customer-number');
            const editPassword = document.getElementById('cr-edit-customer-password');

            function openEdit(button) {
                const updateUrl = button.getAttribute('data-update-url') ?? '';
                if (editForm && updateUrl) editForm.action = updateUrl;
                if (editFullName) editFullName.value = button.getAttribute('data-full-name') ?? '';
                if (editEmail) editEmail.value = button.getAttribute('data-email') ?? '';
                if (editBirthday) editBirthday.value = button.getAttribute('data-birthday') ?? '';
                if (editNumber) editNumber.value = button.getAttribute('data-number') ?? '';
                if (editPassword) editPassword.value = '';
                editModal?.classList.remove('hidden-section');
                document.body.classList.add('modal-open');
            }

            function hideEdit() {
                editModal?.classList.add('hidden-section');
                document.body.classList.remove('modal-open');
            }

            document.querySelectorAll('.cr-edit-customer-btn').forEach((btn) => {
                btn.addEventListener('click', () => openEdit(btn));
            });
            closeEdit?.addEventListener('click', hideEdit);
            cancelEdit?.addEventListener('click', hideEdit);
            editModal?.addEventListener('click', (e) => {
                const t = e.target;
                if (t instanceof HTMLElement && t.dataset.crCloseEditCustomer === 'true') hideEdit();
            });

            function openAdd() {
                addModal?.classList.remove('hidden-section');
                document.body.classList.add('modal-open');
            }

            function hideAdd() {
                addModal?.classList.add('hidden-section');
                document.body.classList.remove('modal-open');
            }

            document.getElementById('cr-open-add-customer')?.addEventListener('click', openAdd);
            closeAdd?.addEventListener('click', hideAdd);
            cancelAdd?.addEventListener('click', hideAdd);
            addModal?.addEventListener('click', (e) => {
                const t = e.target;
                if (t instanceof HTMLElement && t.dataset.crCloseAddCustomer === 'true') hideAdd();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (editModal && !editModal.classList.contains('hidden-section')) hideEdit();
                if (addModal && !addModal.classList.contains('hidden-section')) hideAdd();
            });

            @if ($errors->any() && old('return_to') === $crAddReturnTo && $crShowAdd)
                openAdd();
            @endif
        })();
    </script>
@endonce
