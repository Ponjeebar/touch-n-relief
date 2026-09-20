<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\User;
use App\Support\WalkInSchema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalkInClientService
{
    /**
     * Resolve an existing account or create one for walk-in / staff bookings.
     *
     * Every booking requires a valid users.id FK. Walk-ins receive a dedicated
     * account (walkin.*@walkin.local) when no email/phone match is found.
     */
    public function resolveUser(string $clientName, ?string $email = null, ?string $phone = null): User
    {
        $clientName = trim($clientName) !== '' ? trim($clientName) : 'Walk-in Guest';
        $email = $email !== null ? strtolower(trim($email)) : null;
        $phone = $phone !== null ? trim($phone) : null;

        if ($email === '') {
            $email = null;
        }

        if ($phone === '') {
            $phone = null;
        }

        $existing = $this->findExistingUser($email, $phone, $clientName);
        if ($existing instanceof User) {
            $this->syncWalkInProfile($existing, $clientName, $phone);
            $this->ensureCustomerRecord($existing, $clientName, $phone);

            return $existing->fresh();
        }

        if ($email !== null) {
            $customer = Customer::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($customer instanceof Customer) {
                return $this->ensureCustomerRecord(
                    $this->createUserFromCustomer($customer, $clientName, $phone),
                    $clientName,
                    $phone,
                )->fresh();
            }
        }

        return $this->createWalkInUser($clientName, $email, $phone);
    }

    /**
     * Create or link a staff-provisioned client account with login credentials.
     *
     * Keeps profile_completed_at null so wellness onboarding runs on first login.
     */
    public function resolveStaffCreatedUser(
        string $clientName,
        string $email,
        string $phone,
        string $password,
        ?string $birthday = null,
        ?string $sex = null,
    ): User {
        $clientName = trim($clientName) !== '' ? trim($clientName) : 'Walk-in Guest';
        $email = strtolower(trim($email));
        $phone = trim($phone);

        if ($email === '' || $phone === '') {
            throw ValidationException::withMessages([
                'client_email' => 'Email and phone are required to create a client account.',
            ]);
        }

        $existing = $this->findExistingUser($email, $phone, $clientName);

        if ($existing instanceof User) {
            if ($existing->isWalkIn()) {
                return $this->upgradeStaffWalkIn($existing, $clientName, $email, $phone, $password, $birthday, $sex);
            }

            $this->syncRegisteredProfile($existing, $clientName, $phone, $birthday, $sex);
            $this->ensureCustomerRecord($existing, $clientName, $phone);

            return $existing->fresh();
        }

        return $this->createStaffProvisionedUser($clientName, $email, $phone, $password, $birthday, $sex);
    }

    /**
     * @param  array{name: string, username: string, email: string, contact_number: string, birthday: ?string, sex: ?string, password: string}  $signupShape
     */
    private function persistStaffSignupRecords(User $user, array $signupShape): User
    {
        if (Schema::hasTable('registrations') && ! $user->registration()->exists()) {
            $user->registration()->create([
                'name' => $signupShape['name'],
                'username' => $signupShape['username'],
                'email' => $signupShape['email'],
                'contact_number' => $signupShape['contact_number'],
            ]);
        }

        $this->purgeStaleWalkInCustomerRecords();
        $this->ensureRegisteredCustomerFromSignup($user->fresh(), [
            'name' => $signupShape['name'],
            'username' => $signupShape['username'],
            'email' => $signupShape['email'],
            'contact_number' => $signupShape['contact_number'],
            'birthday' => $signupShape['birthday'] ?? now()->subYears(20)->toDateString(),
            'sex' => $signupShape['sex'] ?? User::SEX_MALE,
            'password' => $signupShape['password'],
        ]);

        return $user->fresh();
    }

    private function upgradeStaffWalkIn(
        User $walkIn,
        string $clientName,
        string $email,
        string $phone,
        string $password,
        ?string $birthday,
        ?string $sex,
    ): User {
        $username = User::isWalkInUsername((string) $walkIn->username)
            ? $this->generateRegisteredUsername($email)
            : (string) $walkIn->username;

        $updates = WalkInSchema::markRegistered([
            'name' => $clientName,
            'username' => $username,
            'email' => $email,
            'contact_number' => $phone,
            'password' => $password,
            'role' => User::ROLE_USER,
            'profile_completed_at' => null,
        ]);

        if (Schema::hasColumn('users', 'birthday') && $birthday !== null && $birthday !== '') {
            $updates['birthday'] = $birthday;
        }

        if ($sex !== null && $sex !== '') {
            $updates['sex'] = $sex;
        }

        try {
            $walkIn->update($updates);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'client_email' => 'That email or username is already registered.',
                ]);
            }

            throw ValidationException::withMessages([
                'client_name' => 'Unable to create the client account. Please try again.',
            ]);
        }

        return $this->persistStaffSignupRecords($walkIn->fresh(), [
            'name' => $clientName,
            'username' => $username,
            'email' => $email,
            'contact_number' => $phone,
            'birthday' => $birthday,
            'sex' => $sex,
            'password' => $password,
        ]);
    }

    private function createStaffProvisionedUser(
        string $clientName,
        string $email,
        string $phone,
        string $password,
        ?string $birthday,
        ?string $sex,
    ): User {
        $attempts = 0;

        while ($attempts < 5) {
            $attempts++;

            try {
                return DB::transaction(function () use ($clientName, $email, $phone, $password, $birthday, $sex): User {
                    $username = $this->generateRegisteredUsername($email);

                    $attributes = WalkInSchema::markRegistered([
                        'name' => $clientName,
                        'username' => $username,
                        'email' => $email,
                        'contact_number' => $phone,
                        'password' => $password,
                        'role' => User::ROLE_USER,
                        'profile_completed_at' => null,
                    ]);

                    if (Schema::hasColumn('users', 'birthday') && $birthday !== null && $birthday !== '') {
                        $attributes['birthday'] = $birthday;
                    }

                    if ($sex !== null && $sex !== '') {
                        $attributes['sex'] = $sex;
                    }

                    $user = User::query()->create($attributes);

                    return $this->persistStaffSignupRecords($user, [
                        'name' => $clientName,
                        'username' => $username,
                        'email' => $email,
                        'contact_number' => $phone,
                        'birthday' => $birthday,
                        'sex' => $sex,
                        'password' => $password,
                    ]);
                });
            } catch (QueryException $exception) {
                if ($this->isUniqueConstraintViolation($exception)) {
                    $resolved = $this->findExistingUser($email, $phone, $clientName);
                    if ($resolved instanceof User) {
                        if ($resolved->isWalkIn()) {
                            return $this->upgradeStaffWalkIn($resolved, $clientName, $email, $phone, $password, $birthday, $sex);
                        }

                        $this->syncRegisteredProfile($resolved, $clientName, $phone, $birthday, $sex);
                        $this->ensureCustomerRecord($resolved, $clientName, $phone);

                        return $resolved->fresh();
                    }

                    continue;
                }

                throw ValidationException::withMessages([
                    'client_name' => 'Unable to create the client account. Please try again.',
                ]);
            }
        }

        throw ValidationException::withMessages([
            'client_name' => 'Unable to create the client account. Please try again.',
        ]);
    }

    private function syncRegisteredProfile(
        User $user,
        string $clientName,
        string $phone,
        ?string $birthday,
        ?string $sex,
    ): void {
        $updates = [];

        if ($clientName !== '' && trim((string) $user->name) === '') {
            $updates['name'] = $clientName;
        }

        if (Schema::hasColumn('users', 'contact_number') && $phone !== '') {
            $updates['contact_number'] = $phone;
        }

        if (Schema::hasColumn('users', 'birthday') && $birthday !== null && $birthday !== '' && $user->birthday === null) {
            $updates['birthday'] = $birthday;
        }

        if ($sex !== null && $sex !== '' && $user->sex === null) {
            $updates['sex'] = $sex;
        }

        if ($updates !== []) {
            $user->update($updates);
        }
    }

    public function findExisting(?string $email = null, ?string $phone = null, ?string $clientName = null): ?User
    {
        $email = $email !== null ? strtolower(trim($email)) : null;
        $phone = $phone !== null ? trim($phone) : null;
        $clientName = $clientName !== null ? trim($clientName) : null;

        if ($email === '') {
            $email = null;
        }

        if ($phone === '') {
            $phone = null;
        }

        if ($clientName === '') {
            $clientName = null;
        }

        return $this->findExistingUser($email, $phone, $clientName);
    }

    /**
     * Ensure a registered users row exists for a customer record (bookings require users.id).
     */
    public function ensureRegisteredUserForCustomer(Customer $customer): User
    {
        if (! Schema::hasTable('users')) {
            throw ValidationException::withMessages([
                'client_name' => 'User accounts are unavailable right now.',
            ]);
        }

        $email = strtolower(trim((string) $customer->email));
        if ($email === '' || User::isWalkInEmail($email)) {
            throw ValidationException::withMessages([
                'client_email' => 'This customer record cannot be linked to a registered account.',
            ]);
        }

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existing instanceof User) {
            if ($existing->isWalkIn()) {
                throw ValidationException::withMessages([
                    'client_email' => 'This email belongs to a walk-in account. Use New Client instead.',
                ]);
            }

            $updates = [];
            if (trim((string) $existing->name) === '' && trim((string) $customer->full_name) !== '') {
                $updates['name'] = $customer->full_name;
            }
            if (Schema::hasColumn('users', 'contact_number')
                && trim((string) ($existing->contact_number ?? '')) === ''
                && trim((string) ($customer->number ?? '')) !== '') {
                $updates['contact_number'] = $customer->number;
            }
            if (Schema::hasColumn('users', 'birthday')
                && $existing->birthday === null
                && $customer->birthday !== null) {
                $updates['birthday'] = $customer->birthday;
            }

            if ($updates !== []) {
                $existing->update($updates);
            }

            return $existing->fresh();
        }

        return $this->createRegisteredUserFromCustomer($customer);
    }

    /**
     * Find a walk-in account that should become the registered account when the guest signs up.
     */
    public function findWalkInForRegistration(string $email, string $phone, string $name): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        $email = strtolower(trim($email));
        $phone = trim($phone);
        $normalizedName = $this->normalizeName($name);

        if ($email !== '') {
            $byEmail = User::query()
                ->where('role', User::ROLE_USER)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get()
                ->first(fn (User $user): bool => $user->isWalkIn());

            if ($byEmail instanceof User) {
                return $byEmail;
            }
        }

        if ($phone !== '' && Schema::hasColumn('users', 'contact_number')) {
            $byPhone = User::query()
                ->where('role', User::ROLE_USER)
                ->where('contact_number', $phone)
                ->get()
                ->filter(fn (User $user): bool => $user->isWalkIn())
                ->values();

            if ($byPhone->count() === 1) {
                return $byPhone->first();
            }

            if ($byPhone->isNotEmpty()) {
                if ($normalizedName !== '') {
                    $byName = $byPhone->filter(
                        fn (User $user): bool => $this->namesMatchForWalkInLink((string) $user->name, $name)
                    );

                    if ($byName->count() === 1) {
                        return $byName->first();
                    }

                    if ($byName->isNotEmpty()) {
                        return $this->pickMostRecentWalkIn($byName);
                    }
                }

                return $this->pickMostRecentWalkIn($byPhone);
            }
        }

        if ($normalizedName !== '') {
            return $this->findWalkInByNameForRegistration($name);
        }

        return null;
    }

    /**
     * Convert an existing walk-in account into a registered client account in place.
     *
     * Keeps the same users.id so spa_bookings and history stay linked.
     *
     * @param  array{name: string, username: string, email: string, contact_number: string, birthday: string, sex: string, password: string}  $validated
     */
    public function upgradeToRegisteredAccount(User $walkIn, array $validated): User
    {
        if (! $walkIn->isWalkIn()) {
            throw ValidationException::withMessages([
                'email' => 'Unable to link this registration to the walk-in record.',
            ]);
        }

        $updates = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => strtolower(trim($validated['email'])),
            'contact_number' => $validated['contact_number'],
            'sex' => $validated['sex'],
            'password' => $validated['password'],
            'role' => User::ROLE_USER,
        ];

        if (Schema::hasColumn('users', 'birthday')) {
            $updates['birthday'] = $validated['birthday'];
        }

        $updates = WalkInSchema::markRegistered($updates);

        try {
            $walkIn->update($updates);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'email' => 'That email or username is already registered.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'Unable to complete registration. Please try again.',
            ]);
        }

        if (Schema::hasTable('registrations') && ! $walkIn->registration()->exists()) {
            $walkIn->registration()->create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => strtolower(trim($validated['email'])),
                'contact_number' => $validated['contact_number'],
            ]);
        }

        $this->purgeStaleWalkInCustomerRecords();

        $this->ensureRegisteredCustomerFromSignup($walkIn->fresh(), $validated);

        return $walkIn->fresh();
    }

    /**
     * Remove customer rows that belong to walk-in guests only.
     */
    public function purgeStaleWalkInCustomerRecords(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('users')) {
            return;
        }

        $usersByEmail = User::query()
            ->whereNotNull('email')
            ->get()
            ->keyBy(fn (User $user): string => strtolower(trim((string) $user->email)));

        Customer::query()
            ->orderBy('id')
            ->get()
            ->each(function (Customer $customer) use ($usersByEmail): void {
                $emailKey = strtolower(trim((string) $customer->email));
                $linkedUser = $usersByEmail->get($emailKey);

                if (User::isWalkInEmail($customer->email)
                    || ($linkedUser instanceof User && $linkedUser->isWalkIn())) {
                    try {
                        $customer->delete();
                    } catch (QueryException) {
                        // Keep page usable if a stale row cannot be removed.
                    }
                }
            });
    }

    /**
     * @param  array{name: string, username: string, email: string, contact_number: string, birthday: string, sex: string, password: string}  $validated
     */
    public function ensureRegisteredCustomerFromSignup(User $user, array $validated): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $email = strtolower(trim((string) $user->email));
        if ($email === '' || User::isWalkInEmail($email)) {
            return;
        }

        $customer = Customer::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($customer instanceof Customer) {
            $customer->update([
                'full_name' => $validated['name'],
                'birthday' => $validated['birthday'],
                'number' => $validated['contact_number'],
                'password' => $user->password,
            ]);

            return;
        }

        try {
            Customer::query()->create([
                'customer_id' => Customer::nextCustomerId(),
                'full_name' => $validated['name'],
                'birthday' => $validated['birthday'],
                'number' => $validated['contact_number'],
                'email' => $email,
                'password' => $user->password,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'email' => 'Unable to save the client record. Please try again.',
                ]);
            }

            Customer::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->update([
                    'full_name' => $validated['name'],
                    'birthday' => $validated['birthday'],
                    'number' => $validated['contact_number'],
                    'password' => $user->password,
                ]);
        }
    }

    /**
     * Confirm the booking's client account still exists in the database.
     *
     * @throws ValidationException
     */
    public function assertValidBookingClient(SpaBooking $booking): User
    {
        $userId = (int) $booking->user_id;

        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'booking' => 'This appointment is missing a linked client account.',
            ]);
        }

        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'booking' => 'The client account for this appointment could not be found.',
            ]);
        }

        return $user;
    }

    private function findExistingUser(?string $email, ?string $phone, ?string $clientName = null): ?User
    {
        if ($email !== null) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user instanceof User) {
                return $user;
            }
        }

        if ($phone !== null && Schema::hasColumn('users', 'contact_number')) {
            $user = User::query()->where('contact_number', $phone)->first();
            if ($user instanceof User) {
                return $user;
            }
        }

        if ($clientName !== null && $this->normalizeName($clientName) !== '') {
            return $this->findWalkInByName($clientName);
        }

        return null;
    }

    private function findWalkInByName(string $clientName): ?User
    {
        $normalized = $this->normalizeName($clientName);
        if ($normalized === '') {
            return null;
        }

        $candidates = User::query()
            ->where('role', User::ROLE_USER)
            ->walkIn()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->orderByDesc('updated_at')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return $this->pickMostRecentWalkIn($candidates);
    }

    private function findWalkInByNameForRegistration(string $clientName): ?User
    {
        $normalized = $this->normalizeName($clientName);
        if ($normalized === '') {
            return null;
        }

        $candidates = User::query()
            ->where('role', User::ROLE_USER)
            ->walkIn()
            ->get()
            ->filter(fn (User $user): bool => $this->namesMatchForWalkInLink((string) $user->name, $clientName))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return $this->pickMostRecentWalkIn($candidates);
    }

    private function namesMatchForWalkInLink(string $existingName, string $incomingName): bool
    {
        $existing = $this->normalizeName($existingName);
        $incoming = $this->normalizeName($incomingName);

        if ($existing === '' || $incoming === '') {
            return false;
        }

        if ($existing === $incoming) {
            return true;
        }

        return str_starts_with($existing, $incoming) || str_starts_with($incoming, $existing);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $candidates
     */
    private function pickMostRecentWalkIn($candidates): ?User
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if (! Schema::hasTable('spa_bookings') || ! Schema::hasColumn('spa_bookings', 'user_id')) {
            return $candidates->first();
        }

        $latestUserId = SpaBooking::query()
            ->whereIn('user_id', $candidates->pluck('id'))
            ->orderByDesc('booking_date')
            ->orderByDesc('time_slot')
            ->value('user_id');

        if ($latestUserId !== null) {
            $match = $candidates->firstWhere('id', (int) $latestUserId);
            if ($match instanceof User) {
                return $match;
            }
        }

        return $candidates->first();
    }

    private function syncWalkInProfile(User $user, string $clientName, ?string $phone): void
    {
        if (! $user->isWalkIn() && ! User::isWalkInEmail($user->email)) {
            return;
        }

        $updates = [];

        if ($clientName !== '' && $clientName !== 'Walk-in Guest') {
            $updates['name'] = $clientName;
        }

        if ($phone !== null && trim((string) ($user->contact_number ?? '')) === '') {
            $updates['contact_number'] = $phone;
        }

        $updates = WalkInSchema::markWalkIn($updates);

        if ($updates !== []) {
            $user->update($updates);
        }
    }

    private function normalizeName(string $name): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($name));

        return strtolower($normalized ?? '');
    }

    private function createWalkInUser(string $clientName, ?string $email, ?string $phone): User
    {
        $attempts = 0;

        while ($attempts < 5) {
            $attempts++;

            try {
                return DB::transaction(function () use ($clientName, $email, $phone): User {
                    $walkEmail = $email ?? $this->generateWalkInEmail();

                    $existing = User::query()->whereRaw('LOWER(email) = ?', [strtolower($walkEmail)])->first();
                    if ($existing instanceof User) {
                        $this->syncWalkInProfile($existing, $clientName, $phone);

                        return $this->ensureCustomerRecord($existing, $clientName, $phone);
                    }

                    $user = User::query()->create(WalkInSchema::markWalkIn([
                        'name' => $clientName,
                        'username' => $this->generateWalkInUsername(),
                        'email' => $walkEmail,
                        'contact_number' => $phone,
                        'password' => Hash::make(Str::password(32)),
                        'role' => User::ROLE_USER,
                        'profile_completed_at' => null,
                    ]));

                    $this->ensureCustomerRecord($user, $clientName, $phone);

                    return $user;
                });
            } catch (QueryException $exception) {
                if ($this->isUniqueConstraintViolation($exception)) {
                    $resolved = $this->findExistingUser($email, $phone, $clientName);
                    if ($resolved instanceof User) {
                        $this->syncWalkInProfile($resolved, $clientName, $phone);

                        return $this->ensureCustomerRecord($resolved, $clientName, $phone);
                    }

                    continue;
                }

                throw ValidationException::withMessages([
                    'client_name' => 'Unable to create the walk-in client record. Please try again.',
                ]);
            }
        }

        throw ValidationException::withMessages([
            'client_name' => 'Unable to create the walk-in client record. Please try again.',
        ]);
    }

    private function createUserFromCustomer(Customer $customer, string $clientName, ?string $phone): User
    {
        return $this->createRegisteredUserFromCustomer($customer, $clientName, $phone);
    }

    private function createRegisteredUserFromCustomer(
        Customer $customer,
        ?string $clientName = null,
        ?string $phone = null,
    ): User {
        $attempts = 0;
        $displayName = trim($clientName ?? '') !== '' ? trim($clientName) : (string) $customer->full_name;
        $contactNumber = trim($phone ?? '') !== '' ? trim($phone) : ($customer->number ?: null);
        $email = strtolower(trim((string) $customer->email));

        while ($attempts < 5) {
            $attempts++;

            try {
                $attributes = WalkInSchema::markRegistered([
                    'name' => $displayName,
                    'username' => $this->generateRegisteredUsername($email),
                    'email' => $customer->email,
                    'contact_number' => $contactNumber,
                    'password' => $customer->password ?: Hash::make(Str::password(32)),
                    'role' => User::ROLE_USER,
                ]);

                if (Schema::hasColumn('users', 'birthday') && $customer->birthday !== null) {
                    $attributes['birthday'] = $customer->birthday;
                }

                return User::query()->create($attributes);
            } catch (QueryException $exception) {
                if ($this->isUniqueConstraintViolation($exception)) {
                    $user = User::query()
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->first();

                    if ($user instanceof User) {
                        return $user;
                    }

                    continue;
                }

                throw ValidationException::withMessages([
                    'client_email' => 'Unable to link this client to an account. Please try again.',
                ]);
            }
        }

        throw ValidationException::withMessages([
            'client_email' => 'Unable to link this client to an account. Please try again.',
        ]);
    }

    private function generateRegisteredUsername(string $email): string
    {
        $localPart = strtolower((string) strtok($email, '@'));
        $base = preg_replace('/[^a-z0-9_]/', '', $localPart) ?: 'client';
        $base = substr($base, 0, 24);
        if (strlen($base) < 3) {
            $base = 'client'.substr(md5($email), 0, 4);
        }

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = substr($base, 0, 24).$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function ensureCustomerRecord(User $user, string $clientName, ?string $phone): User
    {
        $email = strtolower(trim((string) $user->email));

        if ($email === '' || User::isWalkInEmail($email) || $user->isWalkIn()) {
            return $user;
        }

        $customer = Customer::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($customer instanceof Customer) {
            $updates = [];

            if (trim((string) $customer->full_name) === '' && $clientName !== '') {
                $updates['full_name'] = $clientName;
            }

            if ($phone !== null && trim((string) ($customer->number ?? '')) === '') {
                $updates['number'] = $phone;
            }

            if ($updates !== []) {
                $customer->update($updates);
            }

            return $user;
        }

        try {
            Customer::query()->create([
                'customer_id' => Customer::nextCustomerId(),
                'full_name' => $clientName !== '' ? $clientName : (string) $user->name,
                'number' => $phone ?: $user->contact_number,
                'email' => $user->email,
                'password' => $user->password,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw ValidationException::withMessages([
                    'client_name' => 'Unable to save the client record. Please try again.',
                ]);
            }
        }

        return $user;
    }

    private function generateWalkInEmail(): string
    {
        do {
            $email = 'walkin.'.Str::lower(Str::random(12)).'@walkin.local';
        } while (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
            || Customer::query()->whereRaw('LOWER(email) = ?', [$email])->exists());

        return $email;
    }

    private function generateWalkInUsername(): string
    {
        do {
            $username = 'walkin_'.Str::lower(Str::random(8));
        } while (User::query()->where('username', $username)->exists());

        return $username;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = strtolower($exception->getMessage());

        return $code === '23000'
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique');
    }
}
