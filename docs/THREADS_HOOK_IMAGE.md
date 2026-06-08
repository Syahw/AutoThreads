# Hook images on Threads

AutoThreads can attach a **JPEG or PNG** to **Reply 1** (the hook) when publishing or scheduling.

## Meta app setup

**You do not need to change your Threads use case or add new permissions.**

Image posts use the same scope you already have:

- `threads_content_publish` — text and image posts
- `threads_manage_replies` — reply 2+ in the chain

No reconnect required unless you previously skipped `threads_content_publish`.

## How it works

1. Content Generator → draft or approved post → **Upload hook image**
2. Image is stored under `backend/storage/uploads/hook-images/`
3. Served at `{PUBLIC_MEDIA_BASE_URL}/media/{filename}` (public, no auth — Meta downloads it)
4. On publish, Reply 1 uses `media_type=IMAGE` + caption text; replies 2+ stay text

## Public HTTPS URL (required)

Meta must fetch the image from the internet.

### ngrok on port 3000 (Vite + UI tunnel)

1. Run `ngrok http 3000` and `npm run dev`
2. In `.env`:
   ```env
   FRONTEND_URL=https://YOUR.ngrok-free.dev
   PUBLIC_MEDIA_BASE_URL=https://YOUR.ngrok-free.dev
   ```
3. Vite proxies `/media` to Apache (see `frontend/vite.config.js`)
4. Image URLs look like `https://YOUR.ngrok-free.dev/media/{filename}.jpg`

### ngrok on port 80 (Apache tunnel)

1. Run `ngrok http 80`
2. `THREADS_REDIRECT_URI=https://YOUR.ngrok-free.app/autothreads/backend/public/api/v1/threads/callback`
3. `PUBLIC_MEDIA_BASE_URL=https://YOUR.ngrok-free.app/autothreads/backend/public`

If unset, the app uses `PUBLIC_MEDIA_BASE_URL`, then HTTPS `FRONTEND_URL`, then derives from `THREADS_REDIRECT_URI`.

## Limits (Meta)

- JPEG or PNG, max **8 MB**
- Width **320–1440 px** (Meta scales if needed)

## API

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/content/{id}/hook-image` | JWT, multipart field `image` |
| DELETE | `/api/v1/content/{id}/hook-image` | JWT |
| GET | `/media/{filename}` | Public |
