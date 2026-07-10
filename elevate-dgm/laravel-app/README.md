# Elevate DGM Laravel App

Laravel web application for the Elevate DGM dashboards, member records, attendance, feedback, reports, and analytics views.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm

## Fresh Clone Setup

From this directory:

```bash
composer run setup
composer run dev
```

`composer run setup` installs PHP and Node dependencies, copies `.env.example` to `.env`, creates `database/database.sqlite`, runs migrations, and seeds the default users.

## Admin Login

- Email: `admin@elevate.local`
- Password: `password`

If the app already opens but says the admin account cannot be found, run:

```bash
php artisan migrate --seed
```

That will create or repair the default admin account without deleting existing data.

## Useful Commands

```bash
php artisan test
npm run build
php artisan db:seed --class=AdminSeeder
```
