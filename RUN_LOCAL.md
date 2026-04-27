# Run Locally

This project is set up to run on Windows without installing PHP globally.

## First-time setup

Open PowerShell in:

`C:\Aktivno\Pripreme\TEMP\RSFiskFakture`

Run:

```powershell
.\.tools\php\php.exe .\.tools\composer\composer.phar install
npm install
.\.tools\php\php.exe artisan migrate --seed
npm run build
```

## Daily start

One command:

```powershell
.\run-local.cmd
```

Open:

[http://127.0.0.1:8000/](http://127.0.0.1:8000/)

This starts only what you need for normal use:
- Laravel backend on `127.0.0.1:8000`
- built frontend assets from `public/build`

This is the fastest option and avoids waiting for Vite.

## Frontend live editing

If you want live reload while editing the frontend, use:

```powershell
.\run-dev.cmd
```

This starts:
- Laravel backend on `127.0.0.1:8000`
- Vite frontend on `127.0.0.1:5173`

If you prefer to run them separately, you still can:

```powershell
.\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

If you want to run the frontend manually in a second PowerShell window:

```powershell
npm run dev
```

Use the PHP server URL in the browser:

[http://127.0.0.1:8000/](http://127.0.0.1:8000/)

## Notes

- The local database is in `database\database.sqlite`.
- The default queue connection is `sync`, so you do not need a separate queue worker for normal local use.
- OFS local testing can stay on the fake driver unless you switch `.env` to your real OFS device settings.
