# WEF website: Next.js version

This is a 1:1 port of the PHP site in `../website`. Progress is tracked in [PORTING.md](PORTING.md).

## Run locally

```
npm install
cp .env.example .env.local   # fill in what you have; DB_HOST empty = built-in content
npm run dev                  # http://localhost:3100
```

## Settings

All settings are environment variables. They are listed in `.env.example`. Locally
they go in `.env.local`; on Hostinger they go in the Node.js app's environment
variables. Never commit real values.

## Deploy (Hostinger Node.js app, from GitHub)

- Build command: `npm run build`
- Start command: `npm start`
- Keep `uploads/` on the server. Admin uploads are written there. If deploys replace
  the app folder, point `UPLOADS_DIR` (and `STORAGE_DIR`) at a folder outside it.

## Updating the built-in data from the PHP site

```
php scripts/export-php-data.php
```
