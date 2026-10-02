# Elevate DGM Laravel App

Laravel web application for the Elevate DGM dashboards, member records, attendance, feedback, reports, and analytics views.

## Offline thesis evidence

Thesis evidence is paper material, not an admin feature. Generate a fresh report when preparing or auditing the paper:

```bash
php artisan research:thesis-evidence --format=md --output=storage/research/
php artisan research:thesis-evidence --format=json --output=storage/research/
```

The command prints the generated filename. Outputs must stay under `storage/research/`, outside public and signed-download storage disks; linked paths and traversal are rejected. Files have unique names and are not committed. Production refuses to run unless you explicitly add `--force` for an authorized export.

Database access is SELECT-only, enforced before query execution. The command does not synchronize, seed, train, save predictions, update labels/consent, or run tests. It writes the requested offline file (Markdown rendering also uses Laravel's template cache). Reports preserve the evidence tables and audit content while withholding names, contacts, answers and free-text notes. Record identifiers and links are replaced by pseudonymous codes derived with the application key; rotating that key changes the codes. Keep reports within authorized research access.

The strict paper cohort can differ from Sentiment Analytics; neither eligibility rules nor metric math are changed by export. Functional evidence stays UNTESTED when its source fingerprint is stale or missing. To refresh it, separately run `php scripts/verify-thesis-evidence.php`, which executes the suite against isolated in-memory fixtures and writes private verification artifacts. The export command never runs this script automatically.

The former `/admin/research/thesis-evidence` URL permanently redirects to Sentiment Analytics. Its HTML/JSON download endpoint has been removed.

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
