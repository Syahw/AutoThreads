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
3. Served at `{PUBLIC_BASE}/media/{filename}` (public, no auth — Meta downloads it)
4. On publish, Reply 1 uses `media_type=IMAGE` + caption text; replies 2+ stay text

## Public HTTPS URL (required)

Meta must fetch the image from the internet. On local WAMP:

1. Run ngrok (same tunnel as OAuth is fine)
2. `THREADS_REDIRECT_URI` should already point at ngrok
3. Optional override: `PUBLIC_MEDIA_BASE_URL=https://YOUR.ngrok-free.app/AutoThreads/backend/public`

If unset, the app derives the base from `THREADS_REDIRECT_URI`.

## Limits (Meta)

- JPEG or PNG, max **8 MB**
- Width **320–1440 px** (Meta scales if needed)

## API

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/content/{id}/hook-image` | JWT, multipart field `image` |
| DELETE | `/api/v1/content/{id}/hook-image` | JWT |
| GET | `/media/{filename}` | Public |
