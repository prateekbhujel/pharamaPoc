# pharamaPOC

This project is a pharmacy reporting demo built with Laravel and React.

The goal is simple:

- log in with an organization-based account
- filter pharmacy sales with AD and BS dates
- preview rows in a paginated table
- download Excel files without leaving the app
- create, edit, and delete sales
- create and edit pharmacies from the same app

## What Is Inside

- Laravel API routes under `/api/v1/*`
- React app served from `/`
- session login with Laravel Sanctum
- Nepali date picker with BS to AD sync
- report preview with server-side pagination
- background Excel export flow for reports
- direct streaming Excel downloads for sales and pharmacy lists
- sales CRUD with sample import format
- pharmacy CRUD
- Swagger docs

## Project Shape

The code is kept in simple module folders.

Example structure:

```text
app/Modules/
├── Auth/
├── Dashboard/
├── Docs/
├── Pharmacy/
├── Reporting/
├── Sales/
└── User/
```

Each module stays small and familiar:

- `Http/Controllers`
- `Http/Requests`
- `Http/Resources`
- `Models`
- `Repositories`
- `Services`
- `Routes`
- `Providers`

## Local Run

This project uses local Postgres on `127.0.0.1:6464`.

Install everything:

```bash
composer install
npm install
php artisan migrate:fresh --seed
```

One command to prepare the whole app after dependencies are installed:

```bash
php artisan prepare
```

That command can:

- migrate the database fresh
- seed login data
- generate Swagger docs
- scale report rows
- build the frontend

After that, I can benchmark the direct export lanes with:

```bash
php artisan sales:benchmark-export
```

I can benchmark the workbook pipeline with:

```bash
php artisan reporting:benchmark-workbook --date-from=2026-03-01 --date-to=2026-03-23
```

Example with a larger system shape:

```bash
php artisan prepare --rows=5000000 --organizations=400 --hospitals-min=4 --hospitals-max=8 --pharmacies-min=3 --pharmacies-max=6
```

For normal development:

```bash
composer run dev
```

That starts:

- Laravel server
- queue worker
- log tail
- Vite dev server

If you want Laravel to serve the built React app directly:

```bash
npm run build
php artisan serve
```

## Login

All seeded demo accounts use this password:

```text
password
```

Useful accounts:

- `platform.admin`
- `org001.admin`
- `hospital001.admin`

## Demo Data

The seed creates a large sample set with:

- `200` organizations
- `500` hospitals
- `1250` pharmacies
- `280` medicines
- `9000` patients
- `2501` prescribers
- `401` users
- `45000` sales
- `135000` sale items

The sale timeline spans about `15` years.

If you want more local data, increase this value in `.env` and reseed:

```text
PHARMACY_SALES_TARGET=45000
```

If you want a very large benchmark dataset only:

```bash
php artisan reporting:seed-scale 100000000 --batch=500000
```

The seeder can also shape the system bigger:

- more organizations
- more hospitals per organization
- more pharmacies per hospital
- millions of sales rows through the scale command

## Main Pages

`Dashboard`

- see totals quickly
- check current login scope
- open reports or pharmacy work
- review recent export files

`Reports`

- choose date range
- use AD and BS dates together
- filter by organization, hospital, pharmacy, category, supplier, payment status, and cold chain
- preview paginated rows
- use one Excel download button
- start a background Excel job and keep working while it prepares
- watch progress in the UI and auto-download the file when it is ready

`Pharmacies`

- create a pharmacy in a modal
- edit a pharmacy in a modal
- delete with a confirmation modal
- search and filter pharmacies
- use one Excel download button for the pharmacy list
- optionally add a demo sale so the pharmacy shows in preview/export quickly

`Sales`

- create a sale in a modal
- edit or delete a sale in place
- search by invoice, patient, pharmacy, or medicine
- use one Excel download button for the current sales filter
- download a sample CSV format for imports or manual prep

`Exports`

- see the latest running job
- download finished files
- review recent export history

## API Routes

Auth:

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

Reporting:

- `GET /api/v1/reporting/options`
- `GET /api/v1/reporting/preview`
- `GET /api/v1/reporting/export-direct`
- `POST /api/v1/reporting/exports`
- `GET /api/v1/reporting/exports/{publicId}`
- `GET /api/v1/reporting/exports/{publicId}/download`

Pharmacies:

- `GET /api/v1/pharmacies`
- `GET /api/v1/pharmacies/export`
- `POST /api/v1/pharmacies`
- `PUT /api/v1/pharmacies/{pharmacyId}`
- `DELETE /api/v1/pharmacies/{pharmacyId}`

Sales:

- `GET /api/v1/sales`
- `GET /api/v1/sales/export`
- `GET /api/v1/sales/template`
- `POST /api/v1/sales`
- `PUT /api/v1/sales/{saleItemId}`
- `DELETE /api/v1/sales/{saleItemId}`

## Swagger Docs

Generate docs:

```bash
composer run docs:generate
```

Open docs in browser:

```text
/docs/swagger
```

Generated file:

[`public/docs/openapi-v1.json`](/Applications/XAMPP/xamppfiles/htdocs/phar_poc/public/docs/openapi-v1.json)

## Notes

- Report Excel speed comes from Postgres `COPY` extracting to CSV first, then a streaming XLSX writer building the workbook row by row.
- The report preview flow now warms the Excel export in the background, so the actual download click can reuse work that already started.
- Report export jobs run on Laravel's `background` queue connection, so I do not need a separate queue worker just to see progress in the UI.
- Direct report, sales, and pharmacy Excel downloads now use the same streaming workbook builder instead of the heavier old Excel stack.
- True huge XLSX files will still be slower than raw CSV because Excel itself is a zipped workbook format, but this setup removes the slowest PHP overhead from the old path.
- The preview table is paginated on the server, so the browser only gets one page at a time.
- Delete is blocked when a pharmacy already has sales.
- Sales create, update, and delete sync into the live reporting overlay so preview and export change right away.
- The app still uses some older table names in SQL for compatibility, but the UI speaks in organization and hospital terms.
