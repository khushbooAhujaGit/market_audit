{{-- Bottom Navigation (mobile only, hidden on desktop via d-md-none) --}}
<nav class="mobile-bottom-nav d-md-none">
    <a href="{{ route('user.projects') }}"
       class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
        <i class="icon-folder"></i>
        Projects
    </a>
    <a href="javascript:history.back()">
        <i class="icon-arrow-left"></i>
        Back
    </a>
</nav>