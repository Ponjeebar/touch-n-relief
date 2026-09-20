@props(['active' => '', 'spaDashboard' => false])
@php
    $admin = auth()->user()->isAdmin();
    $receptionist = auth()->user()->isReceptionist();
    $homeActive = ($admin || $receptionist) && ($active === 'dashboard' || $active === 'receptionist');
    $cmsActive = in_array($active, ['services', 'therapist-manage', 'landing-settings'], true);
    $operationsActive = in_array($active, ['ongoing', 'completed', 'appointments', 'therapist-monitoring', 'client-records'], true);
    $usersActive = in_array($active, ['users', 'users-receptionists', 'users-customers'], true);
@endphp
<ul class="nav-list">
    @if ($admin || $receptionist)
        <li>
            <a
                href="{{ $admin ? route('dashboard') : route('receptionist.dashboard') }}"
                class="nav-link {{ $homeActive ? 'active' : '' }}"
                @if ($spaDashboard ?? false) id="dashboard-nav-link" @endif
            >
                <span class="nav-icon"><i class="bi bi-speedometer2"></i></span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
    @endif
    @if ($admin)
    <li class="nav-group">
        <details class="nav-group-details" @if ($operationsActive) open @endif>
            <summary class="nav-link nav-group-toggle {{ $operationsActive ? 'active' : '' }}">
                <span class="nav-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                <span class="nav-text">Operations</span>
                <span class="nav-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
            </summary>
            <ul class="nav-sublist">
                <li>
                    <a href="{{ route('ongoing-sessions.index') }}" class="nav-link nav-sublink {{ $active === 'ongoing' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-hourglass-split"></i></span>
                        <span class="nav-text">Ongoing Sessions</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('completed-sessions.index') }}" class="nav-link nav-sublink {{ $active === 'completed' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-check2-circle"></i></span>
                        <span class="nav-text">Completed Sessions</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('appointments.index') }}" class="nav-link nav-sublink {{ $active === 'appointments' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-calendar2-check"></i></span>
                        <span class="nav-text">Appointments</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('therapist-tracking.index') }}" class="nav-link nav-sublink {{ $active === 'therapist-monitoring' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-activity"></i></span>
                        <span class="nav-text">Therapist Monitoring</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('client-records.index') }}" class="nav-link nav-sublink {{ $active === 'client-records' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-folder2-open"></i></span>
                        <span class="nav-text">Client Records</span>
                    </a>
                </li>
            </ul>
        </details>
    </li>
    @else
    <li>
        <a href="{{ route('ongoing-sessions.index') }}" class="nav-link {{ $active === 'ongoing' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-hourglass-split"></i></span>
            <span class="nav-text">Ongoing Sessions</span>
        </a>
    </li>
    <li>
        <a href="{{ route('completed-sessions.index') }}" class="nav-link {{ $active === 'completed' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-check2-circle"></i></span>
            <span class="nav-text">Completed Sessions</span>
        </a>
    </li>
    <li>
        <a href="{{ route('appointments.index') }}" class="nav-link {{ $active === 'appointments' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-calendar2-check"></i></span>
            <span class="nav-text">Appointments</span>
        </a>
    </li>
    <li>
        <a href="{{ route('therapist-tracking.index') }}" class="nav-link {{ $active === 'therapist-monitoring' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-activity"></i></span>
            <span class="nav-text">Therapist Monitoring</span>
        </a>
    </li>
    <li>
        <a href="{{ route('client-records.index') }}" class="nav-link {{ $active === 'client-records' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-folder2-open"></i></span>
            <span class="nav-text">Client Records</span>
        </a>
    </li>
    @endif
    @if ($admin)
    <li class="nav-group">
        <details class="nav-group-details" @if ($usersActive) open @endif>
            <summary class="nav-link nav-group-toggle {{ $usersActive ? 'active' : '' }}">
                <span class="nav-icon"><i class="bi bi-people"></i></span>
                <span class="nav-text">Manage User</span>
                <span class="nav-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
            </summary>
            <ul class="nav-sublist">
                <li>
                    <a
                        href="{{ route('users.index', ['tab' => 'receptionists']) }}"
                        class="nav-link nav-sublink {{ in_array($active, ['users', 'users-receptionists'], true) ? 'active' : '' }}"
                        @if ($spaDashboard ?? false) id="users-nav-link" @endif
                    >
                        <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
                        <span class="nav-text">Receptionists</span>
                    </a>
                </li>
                <li>
                    <a
                        href="{{ route('users.index', ['tab' => 'customers']) }}"
                        class="nav-link nav-sublink {{ $active === 'users-customers' ? 'active' : '' }}"
                    >
                        <span class="nav-icon"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="nav-text">Customers</span>
                    </a>
                </li>
            </ul>
        </details>
    </li>
    @endif
    @if ($admin)
    <li>
        <a href="{{ route('reporting.index') }}" class="nav-link {{ $active === 'reporting' ? 'active' : '' }}">
            <span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span>
            <span class="nav-text">Reports</span>
        </a>
    </li>
    @endif
    @if ($admin)
    <li class="nav-group">
        <details class="nav-group-details" @if ($cmsActive) open @endif>
            <summary class="nav-link nav-group-toggle {{ $cmsActive ? 'active' : '' }}">
                <span class="nav-icon"><i class="bi bi-layout-text-window-reverse"></i></span>
                <span class="nav-text">CMS</span>
                <span class="nav-chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
            </summary>
            <ul class="nav-sublist">
                <li>
                    <a href="{{ route('services.index') }}" class="nav-link nav-sublink {{ $active === 'services' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-grid"></i></span>
                        <span class="nav-text">Services</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('therapist-tracking.index', ['manage' => 1]) }}" class="nav-link nav-sublink {{ $active === 'therapist-manage' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-people"></i></span>
                        <span class="nav-text">Manage Team Profiles</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('landing-settings.edit') }}" class="nav-link nav-sublink {{ $active === 'landing-settings' ? 'active' : '' }}">
                        <span class="nav-icon"><i class="bi bi-window"></i></span>
                        <span class="nav-text">Landing Page</span>
                    </a>
                </li>
            </ul>
        </details>
    </li>
    @endif
</ul>
