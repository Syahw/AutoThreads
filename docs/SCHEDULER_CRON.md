# Scheduled publishing (cron)

AutoThreads stores future publish times in `scheduled_posts`. The **Threads API does not schedule posts** — your server must run `publish_posts.php` to publish when `scheduled_at` is due.

## 1. Configure timezone and posting window

In `backend/.env`:

```env
SCHEDULER_TIMEZONE=Asia/Kuala_Lumpur
SCHEDULER_EARLIEST_HOUR=0
SCHEDULER_LATEST_HOUR=23
SCHEDULER_MAX_POSTS_PER_DAY=5
```

Times picked in the UI are interpreted in `SCHEDULER_TIMEZONE`.

## 2. Run the publish worker

### Linux / VPS (recommended)

Edit crontab (`crontab -e`):

```cron
* * * * * php /var/www/autothreads/backend/cron/publish_posts.php >> /var/www/autothreads/backend/storage/logs/cron.log 2>&1
```

Runs every minute; posts go live within ~1 minute of `scheduled_at`.

Optional daily auto-queue of approved posts:

```cron
0 0 * * * php /var/www/autothreads/backend/cron/auto_schedule.php >> /var/www/autothreads/backend/storage/logs/cron.log 2>&1
```

### Windows (WAMP)

Use **Task Scheduler**:

1. Create Task → Trigger: repeat every **1 minute**.
2. Action: **Start a program**
   - Program: `C:\wamp64\www\AutoThreads\backend\cron\run_publish.bat`
   - Start in: `C:\wamp64\www\AutoThreads\backend`

`run_publish.bat` finds WAMP PHP automatically (Task Scheduler does not have `php` on PATH).

Optional: copy `cron\php-path.bat.example` → `cron\php-path.bat` to pin a PHP version.

Test manually:

```bat
cd C:\wamp64\www\AutoThreads\backend
cron\run_publish.bat
type storage\logs\cron-publish.log
```

## 3. App workflow

1. **Content** → Generate → **Approve**
2. Pick **date & time** → **Schedule** (or **Publish now** for immediate)
3. **Scheduler** page lists queued posts; cancel if needed
4. Cron publishes the thread chain when due

## Requirements

- Threads account connected in **Settings**
- Token includes `threads_manage_replies` for reply chains
- Affiliate link optional; `[link]` is replaced on publish when set, or stripped when not

## How to know if cron is working

### 1. Log file (best signal)

Every run appends lines to:

`backend/storage/logs/cron-publish.log`

You should see a new block **every minute**:

```
===== Task Scheduler run ...
=== publish_posts started ===
Timezone: Asia/Kuala_Lumpur
Now: 2026-06-05 00:06:00
Done: processed=1 published=1 failed=0
=== publish_posts completed ===
```

If this file never updates, Task Scheduler is not running the batch file (wrong path, PHP not in PATH, or task disabled).

### 2. Scheduler page in the app

Open **Scheduler** in AutoThreads:

- **Cron ran in last 3 min** — green badge means the log file was updated recently
- **Due right now** — how many posts are waiting to publish
- **Cron log** — live tail of `cron-publish.log`
- **Run due posts now** — publishes immediately without waiting for Task Scheduler (for testing)

### 3. Manual test in CMD

```bat
cd C:\wamp64\www\AutoThreads\backend
cron\run_publish.bat
type storage\logs\cron-publish.log
```

### Common issues

| Symptom | Fix |
|--------|-----|
| Log file empty | Task not started; use full path to `run_publish.bat`; set "Start in" to `backend` folder |
| `php not in PATH` in log | Use `cron\run_publish.bat` in Task Scheduler (auto-finds WAMP PHP), or set `cron\php-path.bat` |
| `No posts were due` | Normal if scheduled time is still in the future (check server time on Scheduler page) |
| `processed=0` but post queued | `scheduled_at` must be ≤ server time (timezone in `.env`) |
| Publish fails with API error | Reconnect Threads in Settings; check `threads_manage_replies` scope |
| Post stuck on `processing` | Run **Run due posts now** or fix error in log; may need to reset status in DB |
