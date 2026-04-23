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

Run the backend:

```powershell
.\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Open:

[http://127.0.0.1:8000/](http://127.0.0.1:8000/)

## If you are editing the frontend live

Keep the PHP server running, then open a second PowerShell window and run:

```powershell
npm run dev
```

Use the PHP server URL in the browser:

[http://127.0.0.1:8000/](http://127.0.0.1:8000/)

## Notes

- The local database is in `database\database.sqlite`.
- The default queue connection is `sync`, so you do not need a separate queue worker for normal local use.
- OFS local testing can stay on the fake driver unless you switch `.env` to your real OFS device settings.
