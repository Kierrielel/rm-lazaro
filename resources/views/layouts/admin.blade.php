<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="{{ asset('css/header_admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users.css') }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}">
    <link rel="stylesheet" href="{{ asset('css/categories.css') }}">
    <link rel="stylesheet" href="{{ asset('css/edit_product.css') }}">
    <link rel="stylesheet" href="{{ asset('css/order_details.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alert.css') }}">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=search" />

    <title>@yield('title', 'RM LAZARO ENTERPRISES HARDWARE — Admin')</title>
</head>
<body>
    <header>
        <div class="banner">
            <div class="banner-leftside">
                <div class="logo"><img src="{{ asset('images/logo.png') }}" alt="logo" height="50px" width="50px"></div>
                <div class="banner-text">
                    <div class="text-1"><h4>RM LAZARO ENTERPRISES</h4></div>
                    <div class="text-2"><h4>HARDWARE</h4></div>
                </div>
            </div>
            <div class="banner-rightside">
                <button type="button" class="logout-btn" onclick="document.getElementById('logoutModal').showModal()">Logout</button>
            </div>
        </div>
    </header>

    <div class="body-container">
        <div class="sidebar-container">
            <nav>
                <div class="sidebar">
                    <h4>MENU</h4>
                    <a href="{{ route('admin.dashboard') }}"><div class="home"><p class="home-text">Dashboard</p></div></a>
                    <a href="{{ route('admin.inventory') }}"><div class="products"><p class="products-text">Products</p></div></a>
                    <a href="{{ route('admin.categories') }}"><div class="categories"><p class="categories-text">Categories</p></div></a>
                    <a href="{{ route('admin.finance') }}"><div class="finance"><p class="finance-text">Finance Report</p></div></a>
                    <a href="{{ route('admin.orders') }}"><div class="orders"><p class="orders-text">Orders</p></div></a>
                    <a href="{{ route('admin.users') }}"><div class="users"><p class="users-text">Users</p></div></a>
                    <a href="{{ route('admin.settings') }}"><div class="settings"><p class="settings-text">Settings</p></div></a>
                </div>
            </nav>
        </div>

        <div class="main-content">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
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
        </div>
    </div>

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
</body>
</html>