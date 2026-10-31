<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/header.css') }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/products.css') }}">
    <link rel="stylesheet" href="{{ asset('css/product_page.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cart.css') }}">
    <link rel="stylesheet" href="{{ asset('css/account.css') }}">
    <link rel="stylesheet" href="{{ asset('css/edit_user.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alert.css') }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}">

    @stack('styles')

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />

    <title>@yield('title', 'RM LAZARO ENTERPRISES HARDWARE')</title>
</head>
<body class="@stack('body-class')">

    @sectionMissing('hide_layout')
    <header>
        <div class="banner">
            <div class="logo">
                <img src="{{ asset('images/logo.png') }}" alt="logo" height="50px" width="50px">
            </div>

            <div class="banner-text">
                <div class="text-1">
                    <h2>RM LAZARO ENTERPRISES</h2>
                </div>
                <div class="text-2">
                    <h2>HARDWARE</h2>
                </div>
            </div>

            <div class="search-con">
                <form action="{{ route('products.index') }}" method="get">
                    <div class="search-bar">
                        <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor">
                            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
                        </svg>
                        <input
                            class="search-input"
                            type="search"
                            name="search"
                            id="search"
                            placeholder="Search"
                            value="{{ request('search') }}"
                        >
                    </div>
                </form>
            </div>
        </div>

        <div class="banner-2">
            <nav>
                <div class="links-container">
                    <a href="{{ route('home') }}" class="home-link">Home</a>
                    <a href="{{ route('products.index') }}" class="shop-link">Products</a>

                    <a href="{{ route('credit.apply') }}" class="wallet-link" aria-label="Pay Later wallet" title="Pay Later">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H20v14H5a2 2 0 0 1-2-2V7.5A2.5 2.5 0 0 1 5.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M3 8h15.5A2.5 2.5 0 0 1 21 10.5v3a2.5 2.5 0 0 1-2.5 2.5H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="17" cy="12" r="1" fill="currentColor"/>
                        </svg>
                    </a>
                    <a href="{{ route('cart.index') }}" class="cart-link" aria-label="Shopping cart" title="Cart">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.6a2 2 0 0 0 1.9-1.4L21 8H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="10" cy="19" r="1.4" fill="currentColor"/>
                            <circle cx="18" cy="19" r="1.4" fill="currentColor"/>
                        </svg>
                    </a>

                    @auth
                        <a href="{{ route('home') }}" class="user-login">
                            <p>{{ auth()->user()->username }}</p>
                        </a>

                        <button type="button"
                                class="logout-btn"
                                onclick="document.getElementById('logoutModal').showModal()">
                            Logout
                        </button>
                    @else
                        <a href="{{ route('login') }}" class="user-login">
                            <p>Login</p>
                        </a>
                    @endauth
                </div>
            </nav>
        </div>
    </header>
    @endif

    <main>
        @sectionMissing('hide_layout')
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if ($errors->any())
                <div class="alert alert-error">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        @yield('content')
    </main>

    @sectionMissing('hide_layout')
    <footer>
        <div class="store-info-section">
            <div class="store-info-card">
                <h3>Visit Our Store</h3>
                <p>4XPH+86P, Magsaysay Rd, San Miguel, Bulacan</p>
                <p>Monday - Saturday: 7:00 AM - 5:00 PM</p>
                <p>Contact: 0917 982 8985</p>
            </div>
        </div>

        <div id="footer-container">
            <p>&copy; {{ date('Y') }} RM LAZARO ENTERPRISES HARDWARE. All Rights Reserved.</p>
        </div>
    </footer>
    @endif

    {{-- Logout confirmation --}}
    @auth
    <dialog id="logoutModal" class="logout-modal">
        <h3>Log out?</h3>
        <p>Are you sure you want to log out of your account?</p>

        <div class="logout-actions">
            <button type="button" class="btn btn-light" onclick="this.closest('dialog').close()">Cancel</button>

            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit" class="btn btn-danger">Yes, Log out</button>
            </form>
        </div>
    </dialog>
    @endauth
</body>
</html>
