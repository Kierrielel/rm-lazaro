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
                        <span class="search-icon material-symbols-outlined">search</span>
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

                    <a href="{{ route('cart.index') }}" class="cart-link">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             height="24px"
                             viewBox="0 -960 960 960"
                             width="24px"
                             fill="#000000">
                            <path d="M223.5-103.5Q200-127 200-160t23.5-56.5Q247-240 280-240t56.5 23.5Q360-193 360-160t-23.5 56.5Q313-80 280-80t-56.5-23.5Zm400 0Q600-127 600-160t23.5-56.5Q647-240 680-240t56.5 23.5Q760-193 760-160t-23.5 56.5Q713-80 680-80t-56.5-23.5ZM246-720l96 200h280l110-200H246Zm-38-80h590q23 0 35 20.5t1 41.5L692-482q-11 20-29.5 31T622-440H324l-44 80h480v80H280q-45 0-68-39.5t-2-78.5l54-98-144-304H40v-80h130l38 80Zm134 280h280-280Z"/>
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