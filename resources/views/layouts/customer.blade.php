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

                    <a href="{{ route('credit.apply') }}" class="cart-link">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.2" d="M4.23047 3.16016L1.23047 7.55016H19.8305L16.7305 3.33016L4.23047 3.16016Z" fill="white"/>
                            <path d="M1.90039 12.3698C1.90039 10.6698 1.90039 9.8198 2.23039 9.1698C2.51867 8.61481 2.97319 8.16379 3.53039 7.8798C4.17039 7.5498 5.02039 7.5498 6.72039 7.5498H16.2904C19.1204 7.5498 20.5304 7.5498 21.4104 8.4298C22.2904 9.3098 22.2904 10.7298 22.2904 13.5498V14.2098C22.2904 17.0398 22.2904 18.4498 21.4104 19.3298C20.5304 20.2098 19.1104 20.2098 16.2904 20.2098H16.2904H10.1804" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="white"/>
                            <path d="M1.90039 12.0703V9.57031C1.90039 6.74031 1.90039 5.33031 2.78039 4.45031C3.66039 3.57031 5.08039 3.57031 7.90039 3.57031H14.0604C15.8004 3.57031 16.6704 3.57031 17.3804 4.00031C18.0904 4.42031 18.5104 5.19031 19.3404 6.72031L19.5104 7.05031" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="white"/>
                            <path d="M18.441 13.8797C18.441 13.9716 18.4229 14.0626 18.3877 14.1476C18.3526 14.2325 18.301 14.3097 18.236 14.3747C18.171 14.4397 18.0938 14.4912 18.0089 14.5264C17.924 14.5616 17.8329 14.5797 17.741 14.5797C17.6491 14.5797 17.5581 14.5616 17.4731 14.5264C17.3882 14.4912 17.311 14.4397 17.246 14.3747C17.181 14.3097 17.1295 14.2325 17.0943 14.1476C17.0591 14.0626 17.041 13.9716 17.041 13.8797C17.041 13.694 17.1148 13.516 17.246 13.3847C17.3773 13.2534 17.5554 13.1797 17.741 13.1797C17.9267 13.1797 18.1047 13.2534 18.236 13.3847C18.3673 13.516 18.441 13.694 18.441 13.8797Z" fill="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="white"/>
                            <path d="M1.76953 17.5501H4.72953M4.72953 17.5501H7.69953M4.72953 17.5501V20.5301M4.72953 17.5501V14.5801" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke="white"/>
                        </svg>
                    </a>
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