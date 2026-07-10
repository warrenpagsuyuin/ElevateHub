# Elevate DGM

Project scaffold for the Laravel web system and Python ML analytics service.

## Structure

- `laravel-app/` - Laravel web application
- `python-ml/` - Flask-based ML and analytics service

## Laravel Quick Start

```bash
cd laravel-app
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
