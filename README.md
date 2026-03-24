# pharamaPOC

I built `pharamaPOC` around one problem: a user picks a date range, applies a few filters, and expects a very large pharmacy report to download as Excel without the whole system feeling slow.

My real goal was not just to make a small export work. My goal was to make large `.xlsx` reporting feel practical on top of a realistic pharmacy database with organizations, hospitals, pharmacies, medicines, patients, prescribers, payments, and many years of sales data.

I also built this project to make one point very clearly: PHP is not slow just because people say it is, and Laravel is not slow just because the app is large. If the data model is clean, the heavy work is pushed to the database, the export path is streamed properly, and the app avoids unnecessary overhead, PHP and Laravel can handle serious reporting work very well.

## What I Was Solving

In a normal demo, export looks easy because the data is tiny.

In a real pharmacy system, it is not tiny:

- one organization can have many hospitals
- one hospital can have many pharmacies
- one sale can have multiple line items
- medicine, supplier, patient, doctor, and payment details all join together
- the same districts, addresses, medicine names, payment states, and date patterns repeat again and again
- users still want preview, filters, CRUD, and export from one place

That is why I treated this as a data and reporting problem first, not only a UI problem.

I did not want this repo to feel like I was apologizing for PHP. I wanted it to feel like a concrete answer to the usual idea that only Node, Next.js, or some newer stack can handle this kind of workload.

## The Main Idea Behind The Build

I kept the core business tables normalized, created a reporting-friendly materialized view for heavy reads, added an overlay table so live CRUD changes appear quickly, and used PostgreSQL raw SQL plus `COPY` plus OpenSpout to generate Excel with less PHP overhead. When the file is large, the user sees export progress in the UI and can keep using the app while the workbook is prepared in the background.

## What I Wanted To Prove

One big reason I made this project was to show that backend performance problems like this are usually not solved by changing language alone.

For a report like this, the real bottlenecks are usually:

- join depth
- row count
- how much repeated data the system carries
- whether the export reads from a good reporting source or rebuilds everything every time
- whether rows are streamed or buffered badly
- how the `.xlsx` file is built

That means this is not really a "PHP vs JavaScript" argument to me.

If I built the same feature badly in Next.js, it would still be slow.
If I build it carefully in Laravel with PostgreSQL, it can be fast.

So this repo is my way of showing that Laravel can absolutely stand in a serious data-heavy system when I design the data path properly.

## Why These Tables Exist

I did not create tables just to fill a schema diagram. Each table exists because it solves a specific reporting problem.

`tenants`, `hospitals`, `pharmacies`

- I wanted multi-organization data because export performance matters more when the system is shared by many clients.
- The hierarchy also lets me show organization-scoped login and hospital-scoped access in a realistic way.
- A pharmacy does not float by itself in real life. It belongs inside a hospital structure, and the report needs that context.

`location_clusters`

- I knew many values would repeat, especially in Kathmandu-style demo data.
- Instead of repeating the same city, district, area, postal code, and email domain text everywhere, I grouped them in one place.
- This makes the seed more believable and keeps the data model cleaner.

`categories`, `suppliers`, `manufacturers`, `medicines`

- I wanted the export to feel like an actual pharmacy report, not a generic sales spreadsheet.
- Medicines need category, supplier, manufacturer, cold-chain state, strength, dosage form, and price so the filters and joins feel real.

`patients`, `prescribers`

- Real pharmacy sales are tied to people.
- These tables make the report more useful because a user can understand who bought the item and who prescribed it.
- I also intentionally allowed some missing patient and prescriber relationships because real-world data is not always perfectly complete.

`sales`, `sale_items`

- I separated sale header data from sale line data because that is how real transactional systems work.
- Export row count is usually closer to line items than invoices, so `sale_items` is the real reporting pressure point.
- This also lets me show one invoice with multiple medicines instead of a fake one-row sale model.

`pharmacy_sale_export_rows`

- This is the materialized reporting view.
- I created it because I did not want every preview and export to rebuild the full multi-table join from scratch.
- The view stores the reporting-friendly joined shape ahead of time, which makes the hot read path simpler and faster.

`pharmacy_sale_export_overlays`

- This exists so newly created, updated, or deleted sales can show up in reporting immediately without forcing a full materialized view refresh every time.
- I use it as a live patch layer on top of the materialized base.
- That is the reason preview and export can stay responsive even after CRUD activity.

`report_exports`

- I needed one place to track status, progress, file path, row counts, duration, and errors.
- This makes the UI honest because the user can see whether the export is pending, processing, completed, or failed.

## Why I Seeded It This Way

I wrote the seeder to create data that is both believable and useful for performance testing.

I did not want random fake rows with nonsense names because random data is bad at teaching anything. I wanted the seed to look like Nepal pharmacy data, especially around Kathmandu, while still being predictable enough to debug and benchmark.

That is why the seeder does these things:

- creates reusable location clusters for Kathmandu, Lalitpur, and Bhaktapur
- creates many organizations, then many hospitals under them, then many pharmacies under those hospitals
- creates medicine lookups with real pharmacy-style fields like dosage form, strength, pack size, supplier, manufacturer, and cold-chain flags
- creates patients and prescribers so the report has real join depth
- spreads sales across about 15 years so date-range filtering actually means something
- uses repeated but controlled patterns for payment status, payment method, patient assignment, prescriber assignment, and medicine selection
- gives each sale multiple line items because the large export problem usually lives in line-level rows

I used repeated patterns on purpose.

Real systems are full of repetition. The same city repeats. The same supplier repeats. The same medicine repeats. The same payment states repeat. The same hospitals keep appearing in long date windows. I wanted that repetition because it creates a better reporting POC than pure randomness.

I also made the seed deterministic instead of fully random.

That helped me in three ways:

- I can rerun the seed and get a similar shape every time
- I can benchmark changes against a stable dataset
- I can debug export problems without asking whether a random seed changed the outcome

## Why I Added A Separate Scale Command

The normal seeder is for realistic demo data.

The scale command is for stress testing.

I separated those two jobs on purpose.

`php artisan prepare`

- This is my one-command bootstrap.
- It migrates, seeds, generates docs, optionally scales the reporting rows, and builds the frontend.
- I added it because I wanted anyone reviewing this repo to get the full project ready with one command instead of a long checklist.

`php artisan reporting:seed-scale`

- I added this because I wanted to prove that even in a PHP project, I do not need to force PHP to do work the database can do better.
- This command uses PostgreSQL set-based SQL with `generate_series` and batch inserts so the database does the heavy lifting.
- I use the normal seeder to build the realistic foundation, and then I use the scale command to push the transactional tables to large sizes.

`php artisan reporting:benchmark-workbook`

- I added this because performance claims are cheap when they are not measured.
- This command lets me compare the CSV extraction phase against the XLSX-building phase and see where time is actually going.

## Why I Did Not Use Maatwebsite Excel

I did not skip `maatwebsite/excel` because it is bad. I skipped it because my bottleneck was different.

`maatwebsite/excel` is a very good package for many Laravel apps, especially when I want rich Laravel-style export classes, styling helpers, imports, and familiar framework integration.

For this project, I cared more about:

- minimizing memory overhead on very large exports
- controlling the exact export pipeline
- using PostgreSQL `COPY` directly
- turning a fast database-produced CSV into an `.xlsx` workbook with as little extra abstraction as possible

So my choice here was not "package bad". My choice was "this project needs tighter control than the usual package flow gives me."

I wanted fewer layers between the database and the file output, so I stayed closer to the metal instead of using a heavier Excel abstraction.

## Why I Did Not Use FastExcel

I also did not use `FastExcel`, not because it is wrong, but because I needed tighter control than a convenience wrapper.

FastExcel is great when I want a simpler API on top of Spout-style writing. In this project I wanted to handle things like:

- direct CSV-to-XLSX conversion
- progress callbacks during workbook creation
- temp directory control
- Excel sheet rollover at the row limit
- one service that matches my exact report pipeline

Since I already knew I needed OpenSpout-level control, adding another package layer on top did not buy me much.

I wanted the project to show my own thinking about the export path, not hide the interesting part behind too much convenience code.

## Why I Used OpenSpout

I used `OpenSpout` because it fits the exact kind of export problem I was solving.

I needed a writer that:

- streams rows instead of trying to hold the full workbook in memory
- can handle large sheet sizes safely
- is simple enough to plug into a raw SQL pipeline
- gives me direct control over the workbook write process

That made it a better fit for this project than a higher-level export package.

## How I Tried To Make Excel Fast

The most important thing I learned is this:

Huge `.xlsx` files are never just a database problem.

Even if the query is fast, Excel still has to be built as zipped XML files. So I tried to make every stage around that cost as efficient as possible.

This is the export flow I used:

1. Count the filtered rows first so I know what kind of job I am preparing.
2. Read from the reporting source instead of rebuilding the whole business join in application code.
3. Let PostgreSQL write the export-friendly CSV using `COPY`, because the database can stream rows faster than normal PHP loops.
4. Feed that CSV into OpenSpout and stream a real `.xlsx` workbook from it.
5. Split sheets automatically when Excel row limits matter.
6. Store progress in `report_exports` so the UI can show that the file is being prepared.
7. Let the user keep using the system while the export runs in the background when the file is large.

So the speed work here is not one magic trick. It is a combination of:

- normalized source tables
- a pre-joined reporting view
- overlay rows for live changes
- raw SQL for the hot path
- PostgreSQL `COPY`
- streamed workbook creation
- background preparation with progress feedback

That is the part I wanted this repo to prove: Laravel is fast when I stop treating PHP like the only worker in the room and instead let PostgreSQL, streaming I/O, and a better reporting shape carry the expensive parts.

## The Honest Limit

I still kept one honest rule in mind while building this:

CSV will always beat real `.xlsx` when the dataset becomes extremely large.

That is not because the code is weak. That is because `.xlsx` itself is a heavier file format. So my goal here was not to pretend Excel has no cost. My goal was to reduce avoidable cost, move heavy work to the right layer, and make the user experience feel smooth even when the workbook takes time.

## What I Used

I used Laravel 12 for the modular backend, auth, jobs, commands, requests, resources, and API structure; PostgreSQL for the normalized relational data, materialized reporting view, set-based scale inserts, and fast `COPY` export path; React and Vite for the UI; Laravel Sanctum for login state; and OpenSpout for streaming real `.xlsx` files with lower memory pressure than a heavier Excel stack. I used this combination to show that a PHP and Laravel app can handle large reporting workloads seriously when the design is disciplined.

## Local Setup

I used local PostgreSQL on `127.0.0.1:6464`.

To install everything:

```bash
composer install
npm install
php artisan prepare
```

If I want to control the scale:

```bash
php artisan prepare --sales=180000 --rows=1000000 --organizations=200 --hospitals-min=2 --hospitals-max=4 --pharmacies-min=2 --pharmacies-max=4
```

If I want a much larger benchmark:

```bash
php artisan reporting:seed-scale 100000000 --batch=500000
```

For local development:

```bash
composer run dev
```

If I want Laravel to serve the built app directly:

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

## Benchmarks

I added benchmark commands so I can measure export work instead of guessing.

```bash
php artisan reporting:benchmark-workbook --date-from=2026-03-01 --date-to=2026-03-23
php artisan sales:benchmark-export
```

## Final Note

I built this project to show how I think when the real problem is not only CRUD or UI, but data shape, reporting cost, export speed, and system behavior under pressure. I wanted `pharamaPOC` to show that I can model the data properly, seed it in a believable way, scale it beyond the normal demo size, measure the bottlenecks, and still keep the project readable for the next developer. I also wanted it to show, in a concrete way, that PHP and Laravel are not “too slow” for serious reporting work when I design the system properly.
