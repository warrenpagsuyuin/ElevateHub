<nav class="app-navbar">
    <img class="app-logo" src="{{ asset('assets/images/elevate-logo.png') }}" alt="Elevate DGM logo">

    @auth
        <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button type="submit" class="logout-button">Log out</button>
        </form>
    @endauth
</nav>
