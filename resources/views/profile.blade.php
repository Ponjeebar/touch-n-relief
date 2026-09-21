@php
    $profilePhotoUrl = public_storage_url($user->profile_photo_path);
    $transactions = $transactions ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Profile — TouchNRelief</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/profile-app-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/transactions-modal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/password-capslock.css') }}">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <script src="{{ asset('js/password-capslock.js') }}" defer></script>
    <script src="{{ asset('js/password-toggle.js') }}" defer></script>
    <style>
        body{
            font-family:"Inter","Segoe UI",Arial,sans-serif;
            color:#1d2a32;
            -webkit-font-smoothing:antialiased;
            text-rendering:optimizeLegibility;
        }
        .profile-page{
            min-height:100vh;
            padding:7.4rem 0 3.2rem;
            background:#5f737b;
            position:relative;
            overflow:hidden;
        }
        .profile-page::before,
        .profile-page::after{
            content:"";
            position:absolute;
            width:280px;
            height:54px;
            border-radius:999px;
            transform:rotate(-38deg);
            opacity:.78;
            pointer-events:none;
        }
        .profile-page::before{
            background:#d8d866;
            left:-82px;
            top:40px;
        }
        .profile-page::after{
            background:#89b8c9;
            right:-82px;
            bottom:76px;
        }
        .profile-page > .container{
            width:min(1100px,94%);
        }
        .profile-shell{
            max-width:100%;
            margin:0 auto;
            background:#f7f9fb;
            border:1px solid rgba(18,26,32,.18);
            border-radius:10px;
            box-shadow:0 20px 52px rgba(0,0,0,.22);
            overflow:hidden;
            position:relative;
            z-index:1;
        }
        .profile-topbar{
            background:#101114;
            color:#fff;
            padding:.95rem 1.3rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:1rem;
        }
        .profile-topbar strong{
            font-family:"Manrope","Inter",sans-serif;
            font-size:1.05rem;
            font-weight:800;
            letter-spacing:.01em;
        }
        .profile-topbar span{
            color:rgba(255,255,255,.92);
            font-weight:600;
        }
        .profile-cover{
            height:190px;
            background:
                linear-gradient(0deg, rgba(18,30,38,.24), rgba(18,30,38,.24)),
                radial-gradient(circle at 25% 35%, rgba(120,185,205,.6), rgba(78,108,124,.58) 40%, rgba(52,74,85,.74) 100%);
            position:relative;
        }
        .profile-head{
            display:flex;
            align-items:flex-end;
            justify-content:space-between;
            gap:1.5rem;
            padding:0 2rem 1.25rem;
            margin-top:-22px;
            position:relative;
            z-index:2;
            flex-wrap:wrap;
        }
        .profile-txn-teaser{
            margin:0 0 .85rem;
            font-size:.92rem;
            color:#5f7482;
            line-height:1.5;
        }
        .profile-section-compact{
            padding-bottom:1.1rem;
        }
        .profile-id{
            display:flex;
            align-items:center;
            gap:1rem;
            min-height:0;
        }
        .profile-avatar-shell{
            position:relative;
            display:inline-flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
        }
        .profile-meta{
            padding-top:0;
            display:flex;
            align-items:center;
            min-height:124px;
        }
        .profile-avatar{
            width:124px;
            height:124px;
            border-radius:50%;
            border:4px solid #f4f5f7;
            background:linear-gradient(160deg,#dfe7eb,#9ab1bf);
            color:#20313b;
            font-weight:700;
            font-size:2rem;
            display:grid;
            place-items:center;
            box-shadow:0 8px 22px rgba(0,0,0,.24);
            text-transform:uppercase;
            overflow:hidden;
            position:relative;
        }
        .profile-avatar img{
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }
        .profile-avatar-fallback{
            width:100%;
            height:100%;
            display:grid;
            place-items:center;
        }
        .profile-avatar-edit{
            position:absolute;
            left:-8px;
            bottom:8px;
            width:38px;
            height:38px;
            border-radius:50%;
            border:2px solid #ffffff;
            background:#0a7ec2;
            color:#fff;
            display:grid;
            place-items:center;
            cursor:pointer;
            font-size:1.05rem;
            font-weight:700;
            line-height:1;
            box-shadow:0 8px 18px rgba(0,0,0,.36);
            opacity:0;
            pointer-events:none;
            z-index:3;
            transition:opacity .18s ease, transform .18s ease;
            transform:translateY(2px);
        }
        .profile-avatar-shell:hover .profile-avatar-edit,
        .profile-avatar-shell:focus-within .profile-avatar-edit,
        .profile-avatar:hover + .profile-avatar-edit,
        .profile-avatar-edit:focus-visible{
            opacity:1;
            pointer-events:auto;
            transform:translateY(0);
        }
        .profile-avatar-edit:hover{background:#08679f}
        .profile-avatar-input{display:none}
        .profile-photo-save{
            margin-top:10px;
            padding:8px 14px;
            border-radius:8px;
            border:0;
            background:#1f9d58;
            color:#fff;
            font-size:.85rem;
            font-weight:700;
            display:none;
            cursor:pointer;
        }
        .profile-photo-save:hover{
            filter:brightness(1.06);
        }
        .profile-avatar-shell.has-photo-change .profile-photo-save,
        .profile-photo-save:focus-visible{
            display:block;
        }
        .profile-avatar-shell.has-photo-change .profile-avatar-edit{bottom:54px}
        @media (hover:none){
            .profile-avatar-edit{opacity:1;pointer-events:auto;transform:none}
        }
        .profile-title{
            margin:0 0 .35rem;
            font-family:"Manrope","Inter",sans-serif;
            font-size:2.2rem;
            color:#15242d;
            font-weight:800;
            letter-spacing:-.02em;
            line-height:1.08;
        }
        .profile-body{
            padding:2rem 2.25rem 2.5rem;
        }
        .profile-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:1.35rem 1.5rem;
            background:#ffffff;
            border:1px solid #dce8ef;
            border-radius:16px;
            padding:1rem;
            box-shadow:0 6px 18px rgba(18,42,58,.05);
        }
        .profile-password-block{
            margin-top:2rem;
            padding:1.15rem 1.2rem 1.25rem;
            background:linear-gradient(180deg,#ffffff,#fbfdff);
            border:1px solid #dce8ef;
            border-radius:16px;
            box-shadow:0 6px 18px rgba(18,42,58,.05);
        }
        .profile-password-block h3{
            margin:0 0 1rem;
            font-family:"Manrope","Inter",sans-serif;
            font-size:1.02rem;
            font-weight:800;
            color:#15242d;
            letter-spacing:-.01em;
        }
        .profile-password-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:1rem 1.2rem;
        }
        .profile-password-grid .profile-field-span2{
            grid-column:1 / -1;
        }
        .profile-password-wrap{
            position:relative;
        }
        .profile-password-wrap input{
            padding-right:2.9rem;
        }
        .profile-password-toggle{
            position:absolute;
            right:.55rem;
            top:50%;
            transform:translateY(-50%);
            margin:0;
            border:1px solid #d6e3ec;
            background:#f5f9fc;
            color:#5f7482;
            width:2rem;
            height:2rem;
            border-radius:999px;
            display:grid;
            place-items:center;
            cursor:pointer;
            padding:0;
            font-size:.95rem;
            line-height:1;
            z-index:2;
            transition:background-color .15s ease,border-color .15s ease,color .15s ease;
        }
        .profile-password-toggle:disabled{
            opacity:.45;
            cursor:not-allowed;
            pointer-events:none;
        }
        .profile-password-toggle:hover{
            color:#0a7ec2;
            border-color:#9bc2d7;
            background:#eef6fb;
        }
        .profile-field label{
            display:block;
            font-weight:800;
            font-size:.84rem;
            margin-bottom:.5rem;
            color:#233743;
            letter-spacing:.01em;
        }
        .profile-field input{
            width:100%;
            padding:.86rem .96rem;
            border-radius:10px;
            border:1px solid #c6d6e0;
            outline:none;
            background:#fbfdff;
            color:#16252d;
            font-size:.98rem;
            font-weight:500;
            transition:box-shadow .2s ease,border-color .2s ease,background-color .2s ease,transform .12s ease;
        }
        .profile-field textarea{
            width:100%;
            min-height:116px;
            resize:vertical;
            padding:.82rem .92rem;
            border-radius:12px;
            border:1px solid #b9c9d4;
            outline:none;
            background:#fff;
            color:#16252d;
            font-size:.98rem;
            font-family:inherit;
            line-height:1.5;
            transition:box-shadow .2s ease,border-color .2s ease,background-color .2s ease;
        }
        .profile-field input:focus{
            border-color:#2d84af;
            background:#ffffff;
            box-shadow:0 0 0 3px rgba(45,132,175,.16);
            transform:translateY(-1px);
        }
        .profile-field textarea:focus{
            border-color:#2d84af;
            background:#fcfeff;
            box-shadow:0 0 0 3px rgba(45,132,175,.16);
        }
        .profile-field input#age{
            background:linear-gradient(180deg,#f3f9fd,#eef6fb);
            color:#24526c;
            font-weight:700;
            border-color:#b7d0df;
        }
        .profile-field input#age:focus{
            transform:none;
        }
        .profile-field input#birthday{
            background:#ffffff;
            font-weight:600;
        }
        .profile-field input#birthday:read-only{
            background:linear-gradient(180deg,#f3f9fd,#eef6fb);
            color:#24526c;
            border-color:#b7d0df;
            cursor:default;
        }
        .profile-field input#birthday:read-only:focus{
            transform:none;
            box-shadow:none;
            border-color:#b7d0df;
        }
        .profile-field-hint{
            margin-top:.4rem;
            color:#5f7482;
            font-size:.82rem;
            display:flex;
            justify-content:space-between;
            gap:.8rem;
        }
        .profile-field-span2{
            grid-column:1 / -1;
        }
        .profile-actions{
            margin-top:2rem;
            padding-top:1.5rem;
            border-top:1px solid #dce8ef;
            display:flex;
            gap:.85rem;
            align-items:center;
            justify-content:flex-end;
            flex-wrap:wrap;
        }
        .profile-actions .btn{
            padding:.88rem 1.35rem;
            font-weight:800;
            border-radius:999px;
        }
        .profile-local-actions{
            margin-top:.9rem;
            display:flex;
            justify-content:flex-end;
            gap:.55rem;
        }
        .profile-local-actions .btn{
            padding:.62rem 1rem;
            font-weight:700;
            border-radius:999px;
            font-size:.86rem;
        }
        .profile-local-actions .section-edit-btn{
            border:1px solid #8fb6cf;
            background:#eaf4fb;
            color:#1f5f86;
        }
        .profile-local-actions .section-edit-btn:hover{
            background:#dcedf9;
            border-color:#75a3c2;
            color:#184d6e;
        }
        .profile-local-actions .section-cancel-btn{
            display:none;
            border:1px solid #d5dfe7;
            background:#f3f7fa;
            color:#4b6576;
        }
        .profile-local-actions .section-save-btn{
            display:none;
        }
        .profile-local-actions.is-editing .section-cancel-btn{
            display:inline-flex;
        }
        .profile-local-actions.is-editing .section-save-btn{
            display:inline-flex;
        }
        .profile-local-actions.is-editing .section-edit-btn{
            display:none;
        }
        .profile-errors{
            background:#fff7f7;
            border:1px solid rgba(200,70,70,.25);
            color:#8d1f1f;
            border-radius:10px;
            padding:.9rem .98rem;
            margin:.95rem 0;
            line-height:1.5;
        }
        .profile-success-toast{
            position:fixed;
            right:20px;
            top:88px;
            z-index:1800;
            min-width:280px;
            max-width:min(460px,calc(100vw - 32px));
            display:flex;
            align-items:flex-start;
            gap:.6rem;
            background:#f3fff6;
            border:1px solid rgba(40,140,70,.25);
            color:#195e2f;
            border-radius:12px;
            padding:.78rem .9rem;
            box-shadow:0 12px 28px rgba(16,30,25,.16);
            line-height:1.45;
            font-weight:600;
        }
        .profile-success-toast.hidden{
            display:none;
        }
        .profile-success-toast i{
            font-size:1rem;
            margin-top:2px;
        }
        .profile-success-toast-close{
            margin-left:auto;
            border:none;
            background:transparent;
            color:#2d6b45;
            font-size:1rem;
            line-height:1;
            cursor:pointer;
            width:24px;
            height:24px;
            border-radius:6px;
        }
        .profile-success-toast-close:hover{
            background:rgba(45,107,69,.12);
        }
        .profile-section-in-form{
            margin-top:2rem;
        }
        .profile-sections{
            margin-top:2.5rem;
            display:grid;
            gap:1.5rem;
        }
        .profile-section{
            background:#fff;
            border:1px solid #d5e2ea;
            border-radius:14px;
            padding:1.35rem 1.45rem 1.5rem;
        }
        .profile-txn-open{
            margin-top:.25rem;
        }
        .profile-section-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.75rem;
            margin-bottom:.85rem;
        }
        .profile-section-head h2{
            margin:0;
            font-family:"Manrope","Inter",sans-serif;
            font-size:1.05rem;
            font-weight:800;
            color:#15242d;
        }
        .profile-section-head span{
            font-size:.82rem;
            font-weight:600;
            color:#5f7482;
        }
        .profile-wellness{
            background:linear-gradient(165deg,#f8fcfd 0%,#f2f8fa 55%,#eef6f8 100%);
            border-color:#c5dce6;
        }
        .profile-wellness .profile-section-head{
            margin-bottom:1.15rem;
            flex-wrap:wrap;
        }
        .profile-wellness-badge{
            display:inline-flex;
            align-items:center;
            gap:.4rem;
            padding:.35rem .75rem;
            border-radius:999px;
            background:#e8f7ef;
            border:1px solid #b8e6cc;
            color:#1a6b42;
            font-size:.78rem;
            font-weight:700;
        }
        .profile-wellness-badge i{font-size:.95rem}
        .profile-wellness-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:1.25rem 1.75rem;
        }
        .profile-wellness-group{
            display:flex;
            flex-direction:column;
            gap:.65rem;
        }
        .profile-wellness-group.is-span2{
            grid-column:1 / -1;
        }
        .profile-wellness-label{
            display:flex;
            align-items:center;
            gap:.5rem;
            font-weight:700;
            font-size:.9rem;
            color:#1d2f39;
        }
        .profile-wellness-label i{
            color:#1aa3a8;
            font-size:1.05rem;
        }
        .profile-wellness-chips{
            display:flex;
            flex-wrap:wrap;
            gap:.55rem;
        }
        .profile-wellness-chip{
            border:1px solid #c5d8e3;
            background:#fff;
            color:#2a3f4d;
            border-radius:999px;
            padding:.58rem 1rem;
            font-size:.88rem;
            font-weight:600;
            cursor:pointer;
            transition:border-color .15s ease,background-color .15s ease,color .15s ease,box-shadow .15s ease;
        }
        .profile-wellness-chip:hover{
            border-color:#7eb8c9;
            background:#f5fbfc;
        }
        .profile-wellness-chip.is-selected{
            border-color:#1aa3a8;
            background:#e6f6f7;
            color:#0d5c63;
            box-shadow:0 0 0 3px rgba(26,163,168,.14);
        }
        .profile-wellness-hidden{
            position:absolute;
            width:1px;
            height:1px;
            margin:-1px;
            overflow:hidden;
            clip:rect(0,0,0,0);
            border:0;
        }
        .profile-wellness-foot{
            margin-top:1.1rem;
            padding-top:1rem;
            border-top:1px dashed #c5dce6;
            font-size:.86rem;
            color:#5f7482;
            line-height:1.5;
        }
        .profile-wellness-group.is-hidden{
            display:none;
        }
        @media (max-width:720px){
            .profile-wellness-grid{grid-template-columns:1fr}
        }
        .profile-empty{
            margin:0;
            font-size:.92rem;
            color:#5f7482;
            line-height:1.5;
        }
        .profile-txn-list{display:grid;gap:.55rem;width:100%}
        .profile-txn-head,.profile-txn-row{
            display:grid;
            grid-template-columns:84px minmax(110px,1.1fr) minmax(100px,.95fr) 88px 80px 96px;
            gap:.55rem .7rem;
            align-items:center;
        }
        .profile-txn-head{
            font-size:.72rem;
            font-weight:800;
            color:#5f7482;
            text-transform:uppercase;
            letter-spacing:.04em;
            padding:0 .2rem .35rem;
        }
        .profile-txn-row{
            border:1px solid #e8f0ef;
            background:#fbfdfe;
            border-radius:10px;
            padding:.7rem .75rem;
            font-size:.88rem;
            font-weight:600;
            color:#1d2f39;
        }
        .profile-txn-row .mono{
            font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
            font-size:.8rem;
            color:#4f6d8c;
            font-weight:800;
        }
        .profile-txn-row .amount{font-weight:800;color:#2f9d62;text-align:right}
        @media (max-width:900px){
            .profile-grid,
            .profile-password-grid{grid-template-columns:1fr}
            .profile-head{padding:0 1.15rem}
            .profile-body{padding:1.2rem 1.15rem 1.3rem}
            .profile-title{font-size:1.75rem}
            .profile-avatar{width:92px;height:92px;font-size:1.5rem}
            .profile-hint{font-size:.96rem;line-height:1.5}
            .profile-id{min-height:92px}
            .profile-avatar-edit{
                left:-6px;
                bottom:6px;
            }
            .profile-meta{padding-top:14px}
            .profile-meta{
                min-height:92px;
                align-items:center;
            }
            .profile-txn-head,.profile-txn-row{
                grid-template-columns:76px 1fr 88px 80px;
            }
            .profile-txn-head>:nth-child(5),
            .profile-txn-head>:nth-child(6),
            .profile-txn-row>:nth-child(5),
            .profile-txn-row>:nth-child(6){display:none}
        }
    </style>
    @include('partials.chatbot-assets')
</head>
<body>
    <header class="landing-header nav-solid">
        <div class="container">
            <div class="nav-shell">
                <div class="brand-wrap">
                    <img src="{{ asset('images/dashboard/logo.png') }}" alt="Buenos Touche logo" class="brand-logo">
                    <div class="brand">TouchNRelief</div>
                </div>
                <button type="button" class="mobile-nav-toggle" aria-controls="public-mobile-navigation" aria-expanded="false" aria-label="Open navigation menu">
                    <span class="mobile-nav-icon" aria-hidden="true"></span>
                    <span>Menu</span>
                </button>
                <nav class="nav-links" id="public-mobile-navigation">
                    <a href="{{ route('landing') }}">Home</a>
                    @include('partials.customer-notifications')
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-outline" style="cursor:pointer">Logout</button>
                    </form>
                </nav>
            </div>
        </div>
    </header>
    @include('partials.customer-mobile-nav')
    <script src="{{ asset('js/mobile-navigation.js') }}" defer></script>

    <main class="profile-page">
        <div class="container">
            <div class="profile-shell">
                <div class="profile-topbar">
                    <strong>Profile Overview</strong>
                    <span>Last login: {{ optional($user->last_login_at)->format('M d Y') ?? now()->format('M d Y') }}</span>
                </div>
                <div class="profile-cover"></div>
                <div class="profile-head">
                    <div class="profile-id">
                        <form class="profile-avatar-shell" id="profile-photo-form" method="POST" action="{{ route('profile.photo.update') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="profile-avatar">
                                @if ($profilePhotoUrl)
                                    <img
                                        id="profile-avatar-preview"
                                        src="{{ $profilePhotoUrl }}"
                                        alt="{{ $user->name }} profile photo"
                                        onerror="this.style.display='none'; var fb=document.getElementById('profile-avatar-fallback'); if(fb){fb.style.display='flex';}"
                                    >
                                @else
                                    <span id="profile-avatar-fallback" class="profile-avatar-fallback">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</span>
                                    <img id="profile-avatar-preview" src="" alt="{{ $user->name }} profile photo" style="display:none;">
                                @endif
                            </div>
                            <button type="button" class="profile-avatar-edit" id="profile-avatar-edit-btn" aria-label="Change profile photo" title="Change profile photo">&#9998;</button>
                            <input id="profile_photo" class="profile-avatar-input" type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                            <button type="submit" class="profile-photo-save">Save photo</button>
                        </form>
                        <div class="profile-meta">
                            <h1 class="profile-title">{{ $user->name }}</h1>
                        </div>
                    </div>
                </div>
                <div class="profile-body">
                    @if (session('status'))
                        <div class="profile-success-toast" id="profile-status-toast" role="status" aria-live="polite">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                            <button type="button" class="profile-success-toast-close" id="profile-status-toast-close" aria-label="Close">&times;</button>
                        </div>
                    @endif

                    @if ($errors->getBag('profile')->any())
                        <div class="profile-errors">
                            <strong>Please fix the following:</strong>
                            <ul style="margin:.4rem 0 0;padding-left:1.2rem">
                                @foreach ($errors->getBag('profile')->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="edit-profile-form" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="profile-grid" id="basic-info-section">
                            <div class="profile-field">
                                <label for="name">Name</label>
                                <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            </div>
                            <div class="profile-field">
                                <label for="username">Username</label>
                                <input id="username" name="username" value="{{ old('username', $user->username) }}" required>
                            </div>
                            <div class="profile-field">
                                <label for="email">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                            </div>
                            <div class="profile-field">
                                <label for="contact_number">Contact number</label>
                                <input id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric">
                            </div>
                            <div class="profile-field">
                                <label for="birthday">Date of Birth</label>
                                <input
                                    id="birthday"
                                    type="date"
                                    name="birthday"
                                    value="{{ old('birthday', optional($user->birthday)->format('Y-m-d')) }}"
                                    max="{{ now()->toDateString() }}"
                                >
                            </div>
                            <div class="profile-field">
                                <label for="age">Age</label>
                                <input
                                    id="age"
                                    type="text"
                                    value="{{ $user->birthday?->age !== null ? $user->birthday->age : '' }}"
                                    placeholder="Auto-calculated"
                                    readonly
                                    tabindex="-1"
                                >
                            </div>
                        </div>
                        <div class="profile-local-actions" id="basic-info-actions">
                            <button type="button" class="btn btn-light section-edit-btn" id="basic-info-edit">Edit</button>
                            <button type="button" class="btn btn-light section-cancel-btn" id="basic-info-cancel">Cancel</button>
                            <button type="submit" class="btn btn-light section-save-btn" id="basic-info-save">Save changes</button>
                        </div>

                        <div class="profile-password-block" id="password-section">
                            <h3>Change password</h3>
                            <div class="profile-password-grid">
                                <div class="profile-field profile-field-span2">
                                    <label for="current_password">Current password</label>
                                    <div class="profile-password-wrap">
                                        <input id="current_password" type="password" name="current_password" autocomplete="current-password" placeholder="Only if changing password">
                                        <button type="button" class="profile-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="profile-field">
                                    <label for="password">New password</label>
                                    <div class="profile-password-wrap">
                                        <input id="password" type="password" name="password" autocomplete="new-password">
                                        <button type="button" class="profile-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="profile-field">
                                    <label for="password_confirmation">Confirm new password</label>
                                    <div class="profile-password-wrap">
                                        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                                        <button type="button" class="profile-password-toggle" data-pw-toggle aria-label="Show password" aria-pressed="false">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-local-actions" id="password-actions">
                                <button type="button" class="btn btn-light section-edit-btn" id="password-edit">Edit</button>
                                <button type="button" class="btn btn-light section-cancel-btn" id="password-cancel">Cancel</button>
                                <button type="submit" class="btn btn-light section-save-btn" id="password-save">Save changes</button>
                            </div>
                        </div>

                        @php
                            $wellnessSex = old('sex', $user->sex);
                            $wellnessTherapist = old('therapist_gender_preference', $user->therapist_gender_preference);
                            $wellnessPressure = old('pressure_preference', $user->pressure_preference);
                            $wellnessPregnant = old('is_pregnant', $user->is_pregnant ? '1' : '0');
                        @endphp
                        <section class="profile-section profile-section-in-form profile-wellness" id="wellness-section" aria-labelledby="profile-wellness-title">
                            <div class="profile-section-head">
                                <h2 id="profile-wellness-title">Wellness preferences</h2>
                                @if ($user->profile_completed_at)
                                    <span class="profile-wellness-badge">
                                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                        Completed {{ $user->profile_completed_at->format('M d, Y') }}
                                    </span>
                                @endif
                            </div>

                            <div class="profile-wellness-grid">
                                <div class="profile-wellness-group">
                                    <div class="profile-wellness-label">
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                        <span>Sex</span>
                                    </div>
                                    <div class="profile-wellness-chips" data-wellness-chips="sex">
                                        <input class="profile-wellness-hidden" type="radio" name="sex" id="wp-sex-male" value="male" @checked($wellnessSex === 'male')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessSex === 'male') is-selected @endif" data-wellness-chip="sex" data-value="male">Male</button>
                                        <input class="profile-wellness-hidden" type="radio" name="sex" id="wp-sex-female" value="female" @checked($wellnessSex === 'female')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessSex === 'female') is-selected @endif" data-wellness-chip="sex" data-value="female">Female</button>
                                    </div>
                                </div>

                                <div class="profile-wellness-group">
                                    <div class="profile-wellness-label">
                                        <i class="bi bi-hand-index-thumb" aria-hidden="true"></i>
                                        <span>Preferred pressure</span>
                                    </div>
                                    <div class="profile-wellness-chips" data-wellness-chips="pressure_preference">
                                        <input class="profile-wellness-hidden" type="radio" name="pressure_preference" id="wp-pressure-low" value="low" @checked($wellnessPressure === 'low')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessPressure === 'low') is-selected @endif" data-wellness-chip="pressure_preference" data-value="low">Low</button>
                                        <input class="profile-wellness-hidden" type="radio" name="pressure_preference" id="wp-pressure-medium" value="medium" @checked($wellnessPressure === 'medium')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessPressure === 'medium') is-selected @endif" data-wellness-chip="pressure_preference" data-value="medium">Medium</button>
                                        <input class="profile-wellness-hidden" type="radio" name="pressure_preference" id="wp-pressure-high" value="high" @checked($wellnessPressure === 'high')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessPressure === 'high') is-selected @endif" data-wellness-chip="pressure_preference" data-value="high">High</button>
                                    </div>
                                </div>

                                <div class="profile-wellness-group is-span2">
                                    <div class="profile-wellness-label">
                                        <i class="bi bi-people" aria-hidden="true"></i>
                                        <span>Therapist gender preference</span>
                                    </div>
                                    <div class="profile-wellness-chips" data-wellness-chips="therapist_gender_preference">
                                        <input class="profile-wellness-hidden" type="radio" name="therapist_gender_preference" id="wp-therapist-male" value="male" @checked($wellnessTherapist === 'male')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessTherapist === 'male') is-selected @endif" data-wellness-chip="therapist_gender_preference" data-value="male">Male therapist</button>
                                        <input class="profile-wellness-hidden" type="radio" name="therapist_gender_preference" id="wp-therapist-female" value="female" @checked($wellnessTherapist === 'female')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessTherapist === 'female') is-selected @endif" data-wellness-chip="therapist_gender_preference" data-value="female">Female therapist</button>
                                        <input class="profile-wellness-hidden" type="radio" name="therapist_gender_preference" id="wp-therapist-none" value="no_preference" @checked($wellnessTherapist === 'no_preference')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessTherapist === 'no_preference') is-selected @endif" data-wellness-chip="therapist_gender_preference" data-value="no_preference">No preference</button>
                                    </div>
                                </div>

                                <div class="profile-wellness-group is-span2 @if($wellnessSex !== 'female') is-hidden @endif" id="profile-pregnancy-field">
                                    <div class="profile-wellness-label">
                                        <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                                        <span>Currently pregnant</span>
                                    </div>
                                    <div class="profile-wellness-chips" data-wellness-chips="is_pregnant">
                                        <input class="profile-wellness-hidden" type="radio" name="is_pregnant" id="wp-pregnant-no" value="0" @checked($wellnessPregnant == '0')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessPregnant == '0') is-selected @endif" data-wellness-chip="is_pregnant" data-value="0">No</button>
                                        <input class="profile-wellness-hidden" type="radio" name="is_pregnant" id="wp-pregnant-yes" value="1" @checked($wellnessPregnant == '1')>
                                        <button type="button" class="profile-wellness-chip @if($wellnessPregnant == '1') is-selected @endif" data-wellness-chip="is_pregnant" data-value="1">Yes</button>
                                    </div>
                                </div>
                            </div>

                            <p class="profile-wellness-foot">Tap an option to update your preferences, then click <strong>Save changes</strong> below. Changing pregnancy to <strong>No</strong> restores the full service menu on the home page and booking page.</p>
                            <div class="profile-local-actions" id="wellness-actions">
                                <button type="button" class="btn btn-light section-edit-btn" id="wellness-edit">Edit</button>
                                <button type="button" class="btn btn-light section-cancel-btn" id="wellness-cancel">Cancel</button>
                                <button type="submit" class="btn btn-light section-save-btn" id="wellness-save">Save changes</button>
                            </div>
                        </section>

                    </form>

                    <div class="profile-sections">
                        <section class="profile-section profile-section-compact" aria-labelledby="profile-transactions-title">
                            <div class="profile-section-head">
                                <h2 id="profile-transactions-title">Transactions</h2>
                                <span>{{ count($transactions) }} record{{ count($transactions) === 1 ? '' : 's' }}</span>
                            </div>
                            <p class="profile-txn-teaser">View your bookings and completed sessions in a full-screen modal.</p>
                            <button type="button" class="btn btn-light profile-txn-open" data-tnr-open-transactions>Open transactions</button>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('partials.registration-onboarding-modal')
    @include('partials.profile-transactions-modal')
    @include('partials.profile-transactions-script')

<script>
    (function () {
        var form = document.getElementById('edit-profile-form');
        if (!form) return;

        var pregnancyField = document.getElementById('profile-pregnancy-field');
        var editBtn = document.getElementById('profile-avatar-edit-btn');
        var photoInput = document.getElementById('profile_photo');
        var preview = document.getElementById('profile-avatar-preview');
        var fallback = document.getElementById('profile-avatar-fallback');

        editBtn?.addEventListener('click', function () {
            photoInput?.click();
        });

        photoInput?.addEventListener('change', function () {
            var f = photoInput.files && photoInput.files[0];
            if (!f || !preview) return;
            document.getElementById('profile-photo-form')?.classList.add('has-photo-change');
            var url = URL.createObjectURL(f);
            preview.src = url;
            preview.style.display = 'block';
            if (fallback) fallback.style.display = 'none';
        });

        var birthdayInput = document.getElementById('birthday');
        var ageInput = document.getElementById('age');
        var basicInfoSection = document.getElementById('basic-info-section');
        var basicInfoActions = document.getElementById('basic-info-actions');
        var basicInfoEditBtn = document.getElementById('basic-info-edit');
        var basicInfoCancelBtn = document.getElementById('basic-info-cancel');
        var passwordSection = document.getElementById('password-section');
        var passwordActions = document.getElementById('password-actions');
        var passwordEditBtn = document.getElementById('password-edit');
        var passwordCancelBtn = document.getElementById('password-cancel');
        var wellnessSection = document.getElementById('wellness-section');
        var wellnessActions = document.getElementById('wellness-actions');
        var wellnessEditBtn = document.getElementById('wellness-edit');
        var wellnessCancelBtn = document.getElementById('wellness-cancel');

        var basicInfoSnapshot = null;
        var wellnessSnapshot = null;

        function syncPregnancyField() {
            if (!pregnancyField) return;
            var sexInput = form.querySelector('input[name="sex"]:checked');
            var isFemale = sexInput && sexInput.value === 'female';
            pregnancyField.classList.toggle('is-hidden', !isFemale);
            pregnancyField.querySelectorAll('input[name="is_pregnant"]').forEach(function (input) {
                input.disabled = !isFemale;
                if (!isFemale) {
                    input.checked = false;
                }
            });
        }

        form.querySelectorAll('[data-wellness-chips]').forEach(function (group) {
            var name = group.getAttribute('data-wellness-chips');
            group.querySelectorAll('[data-wellness-chip]').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    if (chip.disabled) return;
                    var value = chip.getAttribute('data-value');
                    var input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
                    if (!input) return;
                    input.checked = true;
                    group.querySelectorAll('[data-wellness-chip]').forEach(function (c) {
                        c.classList.toggle('is-selected', c === chip);
                    });
                    if (name === 'sex') syncPregnancyField();
                });
            });
        });

        function setBasicInfoEditing(isEditing) {
            if (!basicInfoSection || !basicInfoActions) return;
            basicInfoActions.classList.toggle('is-editing', isEditing);
            ['name', 'username', 'email', 'contact_number'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.readOnly = !isEditing;
            });
            if (birthdayInput) birthdayInput.readOnly = !isEditing;
        }

        function resetPasswordVisibility() {
            if (typeof window.tnrResetPasswordVisibility === 'function') {
                window.tnrResetPasswordVisibility(passwordSection);
            }
        }

        function setPasswordEditing(isEditing) {
            if (!passwordSection || !passwordActions) return;
            passwordActions.classList.toggle('is-editing', isEditing);
            passwordSection.classList.toggle('is-editing', isEditing);
            ['current_password', 'password', 'password_confirmation'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.disabled = !isEditing;
            });
            passwordSection.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
                btn.disabled = !isEditing;
            });
        }

        function setWellnessEditing(isEditing) {
            if (!wellnessSection || !wellnessActions) return;
            wellnessActions.classList.toggle('is-editing', isEditing);
            wellnessSection.classList.toggle('is-editing', isEditing);
            wellnessSection.querySelectorAll('[data-wellness-chip]').forEach(function (btn) {
                btn.disabled = !isEditing;
            });
            wellnessSection.querySelectorAll('input.profile-wellness-hidden').forEach(function (input) {
                if (!(input instanceof HTMLInputElement)) return;
                if (input.name === 'is_pregnant') return;
                input.disabled = !isEditing;
            });
            if (!isEditing) {
                pregnancyField?.querySelectorAll('input[name="is_pregnant"]').forEach(function (input) {
                    input.disabled = true;
                });
            } else {
                syncPregnancyField();
            }
        }

        function updateAgeFromBirthday() {
            if (!birthdayInput || !ageInput) return;
            if (!birthdayInput.value) {
                ageInput.value = '';
                return;
            }
            var birthDate = new Date(birthdayInput.value + 'T00:00:00');
            if (Number.isNaN(birthDate.getTime())) {
                ageInput.value = '';
                return;
            }
            var today = new Date();
            var age = today.getFullYear() - birthDate.getFullYear();
            var monthDiff = today.getMonth() - birthDate.getMonth();
            var dayDiff = today.getDate() - birthDate.getDate();
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                age -= 1;
            }
            ageInput.value = age >= 0 ? String(age) : '';
        }

        birthdayInput?.addEventListener('change', updateAgeFromBirthday);
        birthdayInput?.addEventListener('input', updateAgeFromBirthday);
        updateAgeFromBirthday();
        basicInfoEditBtn?.addEventListener('click', function () {
            basicInfoSnapshot = {
                name: document.getElementById('name')?.value || '',
                username: document.getElementById('username')?.value || '',
                email: document.getElementById('email')?.value || '',
                contact_number: document.getElementById('contact_number')?.value || '',
                birthday: birthdayInput?.value || '',
            };
            setBasicInfoEditing(true);
        });
        basicInfoCancelBtn?.addEventListener('click', function () {
            if (basicInfoSnapshot) {
                if (document.getElementById('name')) document.getElementById('name').value = basicInfoSnapshot.name;
                if (document.getElementById('username')) document.getElementById('username').value = basicInfoSnapshot.username;
                if (document.getElementById('email')) document.getElementById('email').value = basicInfoSnapshot.email;
                if (document.getElementById('contact_number')) document.getElementById('contact_number').value = basicInfoSnapshot.contact_number;
                if (birthdayInput) birthdayInput.value = basicInfoSnapshot.birthday;
                updateAgeFromBirthday();
            }
            setBasicInfoEditing(false);
        });
        passwordEditBtn?.addEventListener('click', function () {
            setPasswordEditing(true);
        });
        passwordCancelBtn?.addEventListener('click', function () {
            ['current_password', 'password', 'password_confirmation'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            resetPasswordVisibility();
            setPasswordEditing(false);
        });
        wellnessEditBtn?.addEventListener('click', function () {
            wellnessSnapshot = {
                sex: form.querySelector('input[name="sex"]:checked')?.value || '',
                pressure_preference: form.querySelector('input[name="pressure_preference"]:checked')?.value || '',
                therapist_gender_preference: form.querySelector('input[name="therapist_gender_preference"]:checked')?.value || '',
                is_pregnant: form.querySelector('input[name="is_pregnant"]:checked')?.value || '',
            };
            setWellnessEditing(true);
        });
        wellnessCancelBtn?.addEventListener('click', function () {
            if (wellnessSnapshot) {
                ['sex', 'pressure_preference', 'therapist_gender_preference', 'is_pregnant'].forEach(function (name) {
                    var value = wellnessSnapshot[name];
                    var input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
                    if (input) input.checked = true;
                    var group = form.querySelector('[data-wellness-chips="' + name + '"]');
                    group?.querySelectorAll('[data-wellness-chip]').forEach(function (chip) {
                        chip.classList.toggle('is-selected', chip.getAttribute('data-value') === value);
                    });
                });
                syncPregnancyField();
            }
            setWellnessEditing(false);
        });
        setBasicInfoEditing(false);
        setPasswordEditing(false);
        setWellnessEditing(false);
        syncPregnancyField();

        var statusToast = document.getElementById('profile-status-toast');
        var statusToastClose = document.getElementById('profile-status-toast-close');
        if (statusToast) {
            var hideProfileToast = function () {
                statusToast.classList.add('hidden');
            };
            statusToastClose?.addEventListener('click', hideProfileToast);
            window.setTimeout(hideProfileToast, 3200);
        }
    })();
</script>
@include('partials.logout-confirm-modal')
</body>
</html>
