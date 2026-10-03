# RestoSafe — Restaurant Review & Consumer Complaint Platform (CSE447)

Laravel 12 · PHP 8.2+ · MySQL/SQLite · hand-rolled CSS · Chart.js · Google Maps (or Leaflet/OSM fallback) · barryvdh/laravel-dompdf

## Run it (Windows / Herd or any PHP 8.2+)

```bash
composer install                 # vendor is already up to date; this just double-checks
php artisan migrate              # adds the complaint platform tables to your existing DB
php artisan db:seed              # admin (skipped if present) + demo officers/customers/restaurants/complaints
php artisan serve                # http://127.0.0.1:8000
```

Want a clean slate instead? `php artisan migrate:fresh --seed` (deletes all existing data).

PHP extensions needed: `gmp` **or** `bcmath` (big-integer speed), `gd` (blur detection, QR in PDFs), `mbstring`, `dom`, `fileinfo`.
For video uploads raise `upload_max_filesize = 25M` and `post_max_size = 100M` in php.ini.

### Accounts

| Role | E-mail | Password |
|---|---|---|
| Admin | nahin.afrin@g.bracu.ac.bd | AdminPassword123 |
| Officer | officer1@demo.test, officer2@demo.test | DemoPass2026! |
| Customer | customer@demo.test, karim@demo.test, nadia@demo.test | DemoPass2026! |

Login always requires the e-mailed 6-digit OTP (60 s). Demo accounts use the reserved `.test` domain, so no
mail is sent — while `APP_DEBUG=true` the code is shown on the verify screen. Real accounts get it via SMTP.

### Optional
* `GOOGLE_MAPS_API_KEY=` in `.env` → Google Maps for the location picker, restaurant maps and heatmap. Without it, bundled Leaflet + OpenStreetMap is used.
* `TESSERACT_PATH=` → receipt OCR (restaurant name, date, amount). Without it OCR is skipped.

## Where things live

| Feature | Code |
|---|---|
| Custom crypto (BigInteger, SHA-256, HMAC, RSA-OAEP, secp256k1 ECDSA + EC-ElGamal/ECIES) | `app/Services/Crypto/` |
| Key rotation, kid-aware signatures, decrypt with rotated keys | `app/Services/Crypto/KeyManager.php`, `/admin/keys` |
| Token sessions, deactivation enforcement, roles | `app/Http/Middleware/ResolveUser.php`, `AuthenticateSession.php`, `RequireRole.php` |
| Complaint lifecycle + history + e-mail + trust + rewards + audit | `app/Services/ComplaintWorkflow.php`, `app/Support/ComplaintStatus.php` |
| Keyword categorisation | `app/Services/ComplaintClassifier.php`, `app/Support/ComplaintCategory.php` |
| Duplicate detection | `app/Services/DuplicateDetector.php` |
| Trust score (0–5) | `app/Services/TrustScoreService.php` |
| Points & badges | `app/Services/RewardService.php` |
| Blur detection (GD Laplacian variance) & Tesseract OCR | `app/Services/EvidenceAnalyzer.php` |
| PDF report (stored once, SHA-256 recorded) + QR | `app/Services/ReportService.php`, `resources/views/reports/complaint.blade.php` |
| Public QR verification / transparency | `/verify/{ref}?t=…`, `/transparency` |
| Officer queue, analytics, heatmap | `/officer`, `/officer/analytics`, `/officer/heatmap` |
| Admin: officers, users, audit log, review moderation | `/admin/*` |

Review rules kept from the original module: 10 pending reviews max (overflow waitlisted and auto-promoted),
5 reviews per user then a 24 h cooldown, max 3 edits (edit → back to pending), HMAC + ECC verified on every read.
