# pharamaPOC

I built `pharamaPOC` to showcase how I handle large pharmacy reporting data in a way that still feels like a real product, not just a benchmark script.

My main goal in this project was simple:

- handle large multi-organization pharmacy data
- keep the UI understandable for normal users
- support preview, CRUD, filters, and exports from one place
- show how I think about performance, structure, and developer experience together

## What I Built

This project is a Laravel + React pharmacy management and reporting system.

Inside it, I built:

- organization-based login
- hospital and pharmacy scoped access
- sales CRUD
- pharmacy CRUD
- large dataset seeding
- server-side report preview with pagination
- Excel export flow with background preparation
- Swagger docs for the API

## Why I Built It This Way

I wanted this repo to show my thinking, not only the final screen.

So I made a few deliberate choices:

- I used Laravel modules so the backend stays clean and easy to follow.
- I kept React separate from the API flow so the frontend and backend are still clearly divided.
- I used Postgres raw SQL and set-based inserts where scale matters, because looping everything in PHP is not the right answer for huge data.
- I used a streaming XLSX writer path instead of a heavier Excel flow, because large workbook generation needs lower memory overhead.
- I kept the Excel logic inside this app instead of turning it into a separate package, because this repository is meant to showcase the end-to-end system I built, not a standalone library.
- I kept the README in first person because I want this project to read like my work and my decisions.

## Tech Stack

- Laravel 12
- React
- PostgreSQL
- Laravel Sanctum
- OpenSpout
- Vite

## Project Shape

I kept the backend in simple module folders so the structure stays familiar and readable.

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

Inside each module I mostly follow this structure:

- `Http/Controllers`
- `Http/Requests`
- `Http/Resources`
- `Models`
- `Repositories`
- `Services`
- `Routes`
- `Providers`

## Local Setup

I used local Postgres on `127.0.0.1:6464`.

To install everything:

```bash
composer install
npm install
php artisan migrate:fresh --seed
```

I also added one prepare command so I can bootstrap the whole project quickly:

```bash
php artisan prepare
```

That command handles:

- fresh migration
- seeding
- Swagger generation
- report row scaling
- frontend build

If I want a larger seeded shape:

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

If I want Laravel to serve the built React app directly:

```bash
npm run build
php artisan serve
```

## Demo Logins

All seeded demo accounts use:

```text
password
```

Useful accounts:

- `platform.admin`
- `org001.admin`
- `hospital001.admin`

## Demo Data

I prepared the seed to look closer to a real pharmacy system instead of random tiny fake rows.

The default dataset includes:

- `200` organizations
- `500` hospitals
- `1250` pharmacies
- `280` medicines
- `9000` patients
- `2501` prescribers
- `401` users
- `45000` sales
- `135000` sale items

The data timeline spans about `15` years.

If I want to increase the base seeded sales count, I can change this in `.env`:

```text
PHARMACY_SALES_TARGET=45000
```

If I want a very large benchmark dataset:

```bash
php artisan reporting:seed-scale 100000000 --batch=500000
```

That lets me simulate millions of sales/report rows without manually looping inserts one by one in PHP.

## Main Pages

`Dashboard`

- I use this to see totals quickly.
- I can check the current login scope here.
- I can jump into reports, pharmacy work, and recent exports from here.

`Reports`

- I can filter by date, organization, hospital, pharmacy, category, supplier, payment status, and cold chain.
- I can work with both AD and BS dates.
- I can preview rows with server-side pagination.
- I can start an Excel export and keep using the app while it prepares in the background.

`Pharmacies`

- I can create and edit pharmacies in a modal.
- I can search and filter the list.
- I can delete with confirmation.
- I can export the pharmacy list to Excel.

`Sales`

- I can create, edit, and delete sales.
- I can search by invoice, patient, pharmacy, or medicine.
- I can download a sample CSV format for import/reference.
- I can export the filtered sales data to Excel.

`Exports`

- I can see the latest running export job.
- I can track progress.
- I can download completed files.

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

To generate docs:

```bash
composer run docs:generate
```

To open docs in browser:

```text
/docs/swagger
```

Generated file:

[`public/docs/openapi-v1.json`](/Applications/XAMPP/xamppfiles/htdocs/phar_poc/public/docs/openapi-v1.json)

## Performance Notes

This is the part I cared about the most.

I did not want export performance to depend only on queues and hope.
I wanted the data path itself to be stronger.

So here is what I did:

- I used Postgres raw SQL for heavy reporting work.
- I used `COPY` for fast CSV generation from the database side.
- I used a streaming XLSX path so workbook generation does not try to hold everything in memory.
- I used server-side pagination so the browser only receives one page at a time.
- I used background export preparation so the UI feels responsive while the file is being built.
- I used large-scale seed commands so I can test the system against serious row counts.

At the same time, I also know the honest limit:

- real huge `.xlsx` files will always be slower than raw CSV
- Excel itself is a zipped workbook format, so there is unavoidable overhead
- for massive exports, the best improvement is not only “better PHP code”, but also prewarming, caching, and smarter reuse of prepared files

## Benchmarks

I added benchmark commands so I can measure instead of guessing.

Direct export benchmark:

```bash
php artisan sales:benchmark-export
```

Workbook benchmark:

```bash
php artisan reporting:benchmark-workbook --date-from=2026-03-01 --date-to=2026-03-23
```

## Final Note

I built this project to show that I can take a messy real-world reporting problem and turn it into:

- a cleaner backend structure
- a usable frontend
- realistic seeded data
- measurable performance work
- and a project another developer can open and understand

That is what I wanted `pharamaPOC` to represent.
