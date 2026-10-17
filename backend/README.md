# EGYCON Cosplay Registration

Dynamic registration forms (think Google Forms) for cosplay shows and on-site judging.
Laravel 13 · Inertia · React · MySQL · queue worker (Redis or database).

## First-time setup

```bash
composer setup                 # install, .env, key, migrate, npm build
php artisan storage:link       # uploads are served from storage/app/public/uploads
php artisan admin:create you@egycon.com --role=admin     # prompts for a password (12+ chars)
php artisan db:seed            # optional: demo event + demo form (prints a generated admin password)
```

Run during development: `php artisan serve`, `npm run dev` and **`php artisan queue:work`**
(videos are processed in the background - without a worker they stay "pending" and keep the original upload).

## Roles

| Role | Can do |
|---|---|
| `admin` | everything |
| `manager` | create/edit events and forms, review/approve/delete entries |
| `judge` | read-only: dashboard, events, entries |

Create staff with `php artisan admin:create <email> --role=manager|judge|admin`. Roles are enforced on the
server by the `admin.role` middleware (see `routes/web.php`); the UI only hides buttons for convenience.

## Uploads: how they are handled

* Type is detected from the file **content**, checked against an allow-list in `config/media.php`, and the stored
  extension comes from that allow-list - never from the filename. Scripts, HTML and SVG are refused.
* Managers set limits per field in the builder (size, accepted types, image max dimension, video length/resolution).
  A blank size falls back to the defaults in `config/media.php` - blank never means unlimited.
* Images are resized and converted to WebP immediately.
* **Videos** are checked with `ffprobe` during the upload (readable? too long? too short?) and then, in a queued job
  (`ProcessVideoUpload`), trimmed / downscaled / converted to H.264 + AAC MP4 with `ffmpeg`.
  Over-length videos are either **rejected** or **auto-trimmed**, chosen per field.
* PHP's own limits win over form settings. Set in `php.ini` (and nginx `client_max_body_size`):
  `upload_max_filesize` and `post_max_size` at least as large as your biggest video limit. The builder warns you.

### Production checklist

* Install `ffmpeg` and `ffprobe` on the server, set `MEDIA_REQUIRE_FFMPEG=true` (refuse videos if they are missing).
* Run the queue worker under a supervisor (Supervisor / systemd), restart it on every deploy: `php artisan queue:restart`.
* `APP_DEBUG=false`, `APP_ENV=production`, a real `APP_URL` and `APP_TIMEZONE=Africa/Cairo`.

## Voting-system integration

Read-only JSON API, off until `VOTING_API_TOKEN` is set in `.env`
(`php -r "echo bin2hex(random_bytes(32));"`). Send `Authorization: Bearer <token>`.

```
GET /api/v1/forms/{form}/entries                 ?status=approved|pending|rejected|all   (default approved)
                                                 &updated_since=2026-08-15T09:00:00Z
GET /api/v1/forms/{form}/entries/{entry_number}
```

`entry_number` is the stable id: unique per form, never reused, shown to the cosplayer on their profile.
Each entry has `cosplayer`, `answers[]` (`key`, `label`, `type`, `value`) and `media` (`images`, `videos`, `files`
with URLs; videos include `processing` = `ready` once normalised for the show). Email addresses are deliberately not exposed.
Only entries a manager marked **approved** are returned by default.

## Tests

```bash
php artisan test
# video tests need ffmpeg; point at custom binaries if they are not on PATH:
FFMPEG_BINARY=/path/ffmpeg FFPROBE_BINARY=/path/ffprobe php artisan test
```

Format PHP before opening a PR: `./vendor/bin/pint`.
