# Elevate DGM

Project scaffold for the Laravel web system and Python ML analytics service.

Generated reports and screenshots in `outputs/` are not needed to run the application and are not included in the repository. Generate current reports through the application's reporting pages. Dependencies, local environment settings, SQLite databases, compiled assets, logs, and caches are also excluded from Git; the setup command below creates the local runtime files.

## Laravel Quick Start

```bash
cd elevate-dgm/laravel-app
composer run setup
composer run dev
```

Admin login:

- Email: `admin@elevate.local`
- Password: `password`

If an existing clone shows "We could not find an account with that email.", run:

```bash
php artisan migrate --seed
```
