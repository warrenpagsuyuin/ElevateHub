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

## UI Theme Attribution — CS 114

Elevate DGM uses **Tailwind CSS 4.3.2**, by **Tailwind Labs, Inc.**, as an open-source, unstyled CSS foundation for **Direction A: Minimal / Neutral**. The interface uses the project's own Laravel Blade components and CSS theme; it is not a Tailwind Plus template.

- Upstream repository: <https://github.com/tailwindlabs/tailwindcss>
- Version-specific license: [MIT License, v4.3.2](https://github.com/tailwindlabs/tailwindcss/blob/v4.3.2/LICENSE)
- Copyright: Copyright (c) Tailwind Labs, Inc.
- Local license notice: [THIRD_PARTY_NOTICES/Tailwind-CSS-LICENSE.txt](THIRD_PARTY_NOTICES/Tailwind-CSS-LICENSE.txt)
- Verification date: September 16, 2026. The installed package metadata, lockfile, and official version-specific LICENSE identify version 4.3.2 and the MIT license.

The MIT license permits use, modification, and distribution, subject to retaining its copyright and permission notice in copies or substantial portions of the software. The software is supplied without warranty. The full license notice is retained in this repository and in the installed package.

Project customization consists of application-level color, typography, spacing, radius, responsive-layout, and interaction-state styles. Tailwind's upstream source and license headers were not edited for this submission. The Tailwind theme and utilities are integrated through Vite; its global Preflight reset is omitted to preserve the established Blade layouts. No paid Tailwind Plus components are included in this theme selection. This notice describes Tailwind CSS only and does not relicense Elevate DGM or its existing branding assets.
