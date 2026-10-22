# RealityLog

**Remember Everything That Matters.**<br>
**গুরুত্বপূর্ণ কিছুই আর ভুলে যাবেন না।**

RealityLog is a private, bilingual life-memory vault for capturing receipts, documents, conversations, places, purchases, reminders, and everyday moments. It combines a Laravel web application with a Flutter Android client and runs its AI processing locally, so captured content does not need to leave the server for OCR, transcription, or semantic recall.

The project is intentionally free of billing and entitlement checks. All implemented features are available without a paywall.

## Highlights

- **Capture anything** — text, links, images, documents, and voice notes.
- **Local AI processing** — Tesseract OCR for English and Bengali, faster-whisper transcription, and FastEmbed multilingual embeddings.
- **Hybrid recall** — keyword and synonym matching blended with local vector similarity, with deterministic fallback when local AI is unavailable.
- **Memory organization** — types, tags, collections, favorites, archive, attachments, duplicate detection, and linked memories.
- **Relationship discovery** — suggests related memories using semantic similarity plus shared entities, tags, and memory type.
- **Timeline and dashboard** — recent memories, type breakdowns, on-this-day resurfacing, storage usage, and processing status.
- **Reminders** — in-app reminders and an optional scheduled email reminder command.
- **Bilingual interface** — English and Bengali UI strings with a parity test enforcing matching mobile translation keys.
- **Account security** — email verification, password reset, TOTP two-factor authentication, recovery codes, session revocation, hashed API tokens, and audit logging.
- **Portable data** — versioned JSON export of the account, memories, attachments metadata, tags, collections, entities, and reminders.
- **Operational tooling** — database backups, audit-log retention, orphaned attachment cleanup, queue processing, and local AI diagnostics.

## Stack

| Layer | Technology |
| --- | --- |
| Web application | Laravel 13, PHP 8.3+, Blade, Bootstrap 5 |
| Frontend build | Vite 8, npm |
| Mobile application | Flutter, Dart, Android |
| Database | SQLite by default; Laravel-supported MySQL/PostgreSQL |
| Queue | Database queue by default |
| OCR | Tesseract with `eng+ben` language data |
| PDF processing | `smalot/pdfparser`, Poppler `pdftoppm`, FFmpeg |
| Speech-to-text | faster-whisper |
| Embeddings | FastEmbed with `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` |
| Testing | PHPUnit, Flutter Test |

## Repository Layout

```text
app/                 Laravel controllers, models, services, jobs, and commands
config/              Application and RealityLog configuration
database/            Migrations, factories, seeders, and the local SQLite database
lang/                English and Bengali translation files
mobile/              Flutter Android application
public/              Web entry point and compiled assets
resources/           Blade views, CSS, and JavaScript
routes/              Web, API v1, and scheduled command definitions
scripts/ai/          Python dependencies for local AI processing
storage/             Private uploads, AI runtime data, logs, and ignored backups
tests/               PHP feature and unit tests
```

## Requirements

### Web application

- PHP 8.3 or newer with the extensions required by Laravel
- Composer
- Node.js and npm compatible with Vite 8
- SQLite (default) or a configured MySQL/PostgreSQL database

### Mobile application

- Flutter SDK compatible with Dart `^3.13.5`
- Android SDK and an emulator or physical Android device

### Local AI (optional but recommended)

- Python 3
- Tesseract OCR with English and Bengali trained data
- FFmpeg
- Poppler (`pdftoppm`)
- The Python packages in `scripts/ai/requirements.txt`

Local AI is optional. If its binaries or models are unavailable, RealityLog still stores the original capture and marks it for review instead of silently dropping content.

## Getting Started

### 1. Install the web application

```bash
git clone https://github.com/tipusultan007/realitylog.git
cd realitylog
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
composer run setup
```

`composer run setup` installs dependencies, creates `.env` from `.env.example`, generates the application key, runs migrations, installs npm dependencies, and builds frontend assets.

Start the development environment:

```bash
composer run dev
```

Or run the pieces separately:

```bash
php artisan serve
npm run dev
php artisan queue:work
```

### 2. Configure the environment

Edit `.env` for the database, mail, filesystem, and local AI paths. The default configuration uses SQLite and the database queue.

Important local-AI settings:

```dotenv
LOCAL_AI_ENABLED=true
AI_PYTHON=/absolute/path/to/python
TESSERACT_BINARY=tesseract
TESSDATA_DIR=/absolute/path/to/tessdata
OCR_LANGUAGES=eng+ben
FFMPEG_BINARY=ffmpeg
PDFTOPPM_BINARY=pdftoppm
WHISPER_MODEL=base
WHISPER_DEVICE=cpu
WHISPER_COMPUTE_TYPE=int8
EMBEDDING_MODEL=sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2
EMBEDDING_DIMENSIONS=384
```

### 3. Install the local AI runtime

Create a Python virtual environment inside the project:

```bash
python -m venv storage/app/ai-venv
```

Install the Python packages:

```bash
# Windows
storage\app\ai-venv\Scripts\python.exe -m pip install -r scripts/ai/requirements.txt

# macOS/Linux
storage/app/ai-venv/bin/python -m pip install -r scripts/ai/requirements.txt
```

Point `AI_PYTHON` at the virtual environment's Python executable, then verify the runtime:

```bash
php artisan ai:check
php artisan ai:embed
```

Useful AI commands:

| Command | Purpose |
| --- | --- |
| `php artisan ai:check` | Check Python packages, Tesseract, FFmpeg, Poppler, language data, and model availability |
| `php artisan ai:embed` | Generate embeddings for memories that do not have them |
| `php artisan ai:embed --force` | Re-embed every memory |

### 4. Seed demo data (optional)

```bash
php artisan db:seed
```

This creates the demo account `demo@realitylog.app` with password `password`. Set `REALITYLOG_DEMO=true` to expose the demo account on the sign-in screen. Keep demo mode disabled in production.

### 5. Run the mobile app

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` is the Android emulator alias for the host machine. For a physical device, use the host machine's LAN address, for example:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
```

The mobile client stores the API token securely on the device and supports the same capture, search, timeline, reminder, collection, settings, and session flows as the web app.

## API

The REST API is versioned under `/api/v1`. Authentication uses bearer tokens issued by the login endpoint and stored as hashes in the database.

| Area | Endpoints |
| --- | --- |
| Public | `GET /api/v1/meta`, `POST /api/v1/register`, `POST /api/v1/login`, `POST /api/v1/two-factor/challenge` |
| Account | `GET /api/v1/me`, `POST /api/v1/logout`, `GET /api/v1/sessions`, `DELETE /api/v1/sessions`, `DELETE /api/v1/sessions/{token}` |
| Memories | `GET /api/v1/memories`, `GET /api/v1/memories/{memory}`, `PATCH /api/v1/memories/{memory}`, `DELETE /api/v1/memories/{memory}` |
| Memory actions | Favorite, archive, restore, link, unlink, reprocess, and attachment upload/delete |
| Discovery | `GET /api/v1/search`, `GET /api/v1/timeline`, `GET /api/v1/dashboard` |
| Organization | `GET/POST/PATCH/DELETE /api/v1/collections` |
| Reminders | `GET/POST /api/v1/reminders`, complete, snooze, and delete actions |
| Settings | Profile, language, notifications, privacy, and account deletion |
| Data | `GET /api/v1/export` |

All authenticated API requests accept an `X-Locale` header with `en` or `bn`.

## Scheduled Commands

The scheduler is defined in `routes/console.php`:

| Command | Schedule |
| --- | --- |
| `reminders:send-email` | Hourly |
| `backup:database` | Daily at 02:00 |
| `audit:prune` | Monthly on the first day at 03:00 |
| `attachments:prune-orphans` | Weekly |
| `queue:prune-failed --hours=168` | Weekly |

In production, run the scheduler every minute through cron or your process supervisor:

```bash
* * * * * php /path/to/realitylog/artisan schedule:run >> /dev/null 2>&1
```

For local development, `php artisan schedule:work` keeps the scheduler running in the foreground.

## Testing and Quality

```bash
# PHP
php artisan test --compact
vendor/bin/pint --dirty --format agent

# Flutter
cd mobile
flutter analyze
flutter test
```

The PHP suite covers authentication, two-factor flows, email verification, memory capture and management, recall, relationships, reminders, exports, operations, localization, and API behavior. The Flutter suite covers capture drafts, bilingual string parity, and core widget behavior.

## Security and Privacy

- Local AI processing keeps OCR, transcription, and embeddings on the configured server.
- `.env`, SQLite databases, backups, vendor packages, node modules, and build artifacts are excluded from version control.
- Passwords are hashed by Laravel; API tokens are stored as SHA-256 hashes.
- Two-factor secrets and recovery codes are encrypted at rest.
- Email verification uses signed links and checks that the authenticated user owns the verification request.
- Uploads are validated by type and size. Optional ClamAV scanning can be enabled with `UPLOAD_SCANNER=clamav`.
- Audit logs record sensitive account and memory actions.
- Backups use `VACUUM INTO` for SQLite and client binaries for MySQL/PostgreSQL.

Before deploying:

1. Set `APP_ENV=production` and `APP_DEBUG=false`.
2. Configure a real mailer for verification and reminder emails.
3. Run `php artisan migrate --force`.
4. Run a queue worker and the scheduler under a process supervisor.
5. Configure backups and object storage as needed.
6. Keep `REALITYLOG_DEMO=false`.
7. Install ClamAV and local AI dependencies if those capabilities are required.

## Current Scope

- Web application and Android API client are implemented.
- Billing, subscriptions, paywalls, and entitlement checks are intentionally out of scope.
- iOS is deferred until a macOS/CI build environment is available.
- Mail defaults to the `log` driver until SMTP or another transactional mail service is configured.
- ClamAV is optional; the default upload scanner validates metadata but does not inspect file contents.

## License

MIT, as declared in `composer.json`.

## Acknowledgments

RealityLog is built on [Laravel](https://laravel.com), [Flutter](https://flutter.dev), [Tesseract](https://github.com/tesseract-ocr/tesseract), [faster-whisper](https://github.com/SYSTRAN/faster-whisper), and [FastEmbed](https://github.com/qdrant/fastembed).
