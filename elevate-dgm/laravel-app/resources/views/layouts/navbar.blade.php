<nav class="app-navbar">
    <strong>Elevate DGM</strong>

    @auth
        <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button type="submit" class="logout-button">Log out</button>
        </form>
    @endauth
</nav>
