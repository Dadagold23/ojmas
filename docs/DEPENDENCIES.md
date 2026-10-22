# Dependency and Licensing Inventory

Prepared for the Module 5 deliverables. Lockfile versions are the deployed
truth; ranges come from `composer.json` / `package.json`.

## Production (composer)

| Package | Locked | License | Used for |
|---|---|---|---|
| laravel/framework | ^12.0 | MIT | The application skeleton |
| spatie/laravel-permission | 6.25.0 | MIT | Role/permission storage and Gate wiring (Module 3) |
| phpoffice/phpword | 1.3.0 | **LGPL-3.0-only** | DOCX parse/format (incl. under the hood: pclzip/pclzip LGPL-2.1; phpoffice/common LGPL; zend-* BSD-3-Clause) |
| barryvdh/laravel-dompdf | 3.1.2 (dompdf/dompdf v2.x → **LGPL-2.1**) | MIT (wrapper) | Receipt/report PDFs in the pre-existing payment module |
| laravel/tinker | ^2.10 | MIT | Dev/ops tooling |
| minishlink/web-push | v11.0.0 | MIT | Web Push (VAPID) delivery — Addendum Module 3. Under the hood: web-token/jwt-library, php-http/httplug + PSR-7 stack, spomky-labs/base64url (all MIT/BSD-class; `composer lock` is the full truth) |

### LGPL note for the journal

PHPWord and dompdf are **dynamically linked through Composer** and are used as
unmodified libraries. Under LGPL-3.0 / LGPL-2.1 this use does not impose the
library's license on OJMAS application code. No library source was modified;
if either library is ever vendored-and-patched instead, the LGPL obligations
(relink rights, license notice redistribution) start to bite and should be
re-checked at that point.

## Development (composer)

fakerphp/faker (MIT), phpunit/phpunit (BSD-3-Clause), mockery/mockery
(BSD-3-Clause), laravel/breeze (MIT), laravel/pail (MIT), laravel/pint (MIT),
laravel/sail (MIT), nunomaduro/collision (MIT). None deploy to production.

## Front-end (npm)

| Package | Version | License | Used for |
|---|---|---|---|
| @tiptap/core | ^3.32 | MIT | The browser manuscript editor (Module 2, approved) |
| @tiptap/starter-kit | ^3.32 | MIT | Core editing behaviour |
| @tiptap/extension-character-count | ^3.32 | MIT | Word-limit feedback against style profiles |
| @tiptap/extension-placeholder | ^3.32 | MIT | Placeholder text |
| @tiptap/pm | ^3.32 | MIT | ProseMirror bindings |

The build also ships Bootstrap 5 and Bootstrap Icons (MIT) pre-existing in the
theme, plus the Tiptap bundle (~404 kB) loaded only on workspace pages.

## PHP extension requirements (production)

The document pipeline in particular needs:

- `mbstring`, `xml`, `xmlwriter`, `xmlreader`, `dom`, `simplexml` — PHPWord
- `zip` (or `php_zip`-equivalent) — DOCX read/write, ingestion validation
- `fileinfo` — upload MIME validation
- `gd` — only for image handling inside uploaded manuscripts when processing
  is enabled for images; core flows work without it (the application does not
  auto-generate PDFs — see DEPLOYMENT.md)
- standard Laravel set: `pdo_mysql`, `openssl`, `curl`, `tokenizer`

Verify on the server with:

```bash
php -m | sort
composer check-platform-reqs
```

Web Push (Addendum Module 3) additionally requires `ext-curl`, `ext-json`,
`ext-mbstring` and `ext-openssl` for `minishlink/web-push` — all in the same
standard Laravel set above (`gmp` is **not** required; the library signs
VAPID JWTs with `openssl`).

Both must succeed before `composer install --no-dev` on production. **This was
confirmed locally only; production must be re-verified (S9-style gate).**

## External services

- **Paystack** payment gateway — pre-existing integration; its keys are
  runtime configuration (`PAYSTACK_*`), no code changes in Modules 1–4.
- **Mail** — `MAIL_MAILER` runtime configuration; queued notifications
  assume a reachable SMTP endpoint and a running queue worker.
- **Browser push endpoints (Addendum Module 3)** — delivery necessarily
  contacts the vendor endpoints (FCM for Chrome/Firefox, Mozilla autopush,
  Apple's push gateway for Safari) that the Web Push standard routes through.
  Each receives the endpoint URL the browser generated and the encrypted
  payload (which only the subscriber's browser can decrypt); no third party
  can read the content and none is given user-identifying data beyond what
  any Web Push implementation must expose. No credentials are held for them;
  delivery authorisation is the server's own VAPID key (`PUSH_VAPID_*`).

