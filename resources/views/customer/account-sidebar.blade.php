<aside class="account-overview-sidebar">
    <p class="account-sidebar-title">Your account</p>
    <nav aria-label="Account navigation">
        <a href="{{ route('customer.accountOverview') }}"
           class="account-sidebar-link {{ request()->routeIs('customer.accountOverview', 'customer.orders.*') ? 'is-active' : '' }}"
           @if (request()->routeIs('customer.accountOverview', 'customer.orders.*')) aria-current="page" @endif>
            Overview &amp; Orders
        </a>
        <a href="{{ route('customer.settings') }}"
           class="account-sidebar-link {{ request()->routeIs('customer.settings') ? 'is-active' : '' }}"
           @if (request()->routeIs('customer.settings')) aria-current="page" @endif>
            Settings
        </a>
    </nav>
</aside>
