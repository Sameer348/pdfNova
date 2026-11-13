# Deploying PDFNova on Hostinger shared hosting

This guide takes you from an empty Hostinger account to a working, secure PDFNova site.
It assumes a **Premium, Business or Cloud** shared hosting plan (PHP 8.1+, MySQL/MariaDB, cron jobs).

> **About menu names.** Hostinger occasionally moves things around in hPanel. Menu paths below
> (e.g. *hPanel → Advanced → Cron Jobs*) were correct when this guide was written; if one has
> moved, use the search box at the top of hPanel.

**Time needed:** about 30–45 minutes.

**What you need:**
- A Hostinger hosting plan with your domain added (e.g. `yourdomain.com`).
- On your own computer: the PDFNova project folder, PHP 8.1+ and Composer (only to build the
  upload package — see step 1). If you can't install them, ask whoever gave you the project for
  a ready-made `pdfnova-hostinger.zip`.

**Overview**

| # | Step | Where |
|---|------|-------|
| 1 | Build the upload package | your computer |
| 2 | Choose the PHP version and settings | hPanel |
| 3 | Create the database and database user | hPanel |
| 4 | Import the database tables | phpMyAdmin |
| 5 | Upload the files to `public_html` | File Manager |
| 6 | Create `config.php` | File Manager |
| 7 | Check folder permissions | File Manager |
| 8 | Check the `.htaccess` files | File Manager |
| 9 | Turn on SSL (HTTPS) | hPanel |
| 10 | Create the sender mailbox | hPanel |
| 11 | Create your admin account | website + phpMyAdmin |
| 12 | Add the cleanup cron job | hPanel |
| 13 | Run the System check | admin panel |
| 14 | Test uploads, processing and accounts | website |

---

## 1. Build the upload package (on your computer)

The site needs two PHP libraries (FPDI and TCPDF). Composer downloads them into `public_html/vendor/`.
Hostinger doesn't need Composer — you upload the finished `vendor/` folder.

Open a terminal in the project folder (the one containing `composer.json`) and run:

```bash
bash build/package.sh
```

This creates **`pdfnova-hostinger.zip`** (about 15 MB, mostly fonts). The script:

- installs the libraries with `composer install --no-dev --optimize-autoloader`,
- leaves out your local `includes/config.php`, logs and any leftover user files,
- keeps all `.htaccess` protection files and the empty `storage/` folders,
- checks every PHP file for syntax errors, and refuses to build if anything is wrong.

> **Never** upload your local `includes/config.php` — it contains your local database password
> and key. The live site gets its own (step 6).

<details>
<summary>Without the script (manual)</summary>

```bash
composer install --no-dev --optimize-autoloader
```

Then zip the **contents** of `public_html/` (not the folder itself), excluding
`includes/config.php` and everything inside `storage/uploads`, `storage/processed`,
`storage/temp` and `storage/logs` except `.htaccess` and `.gitkeep`.
</details>

---

## 2. PHP version and settings

**hPanel → Advanced → PHP Configuration**

**PHP version tab:** choose **8.3** (8.2 is fine; 8.1 is the minimum). Save.

**PHP extensions tab:** make sure these are ticked (they normally are by default):

| Extension | Needed for | If missing |
|---|---|---|
| `pdo_mysql`, `mbstring`, `fileinfo`, `zlib`, `json` | the site itself | site won't work |
| `gd` | JPG to PDF, image compression | those features switch off |
| `openssl` | Protect PDF, Unlock PDF | those tools switch off |
| `zip` | ZIP download in Split PDF | ZIP option switches off |
| `curl` | optional external converter/OCR services | those stay "not available" |

**PHP options tab:** set

| Option | Recommended | Why |
|---|---|---|
| `upload_max_filesize` | `200M` | largest file the Business plan allows |
| `post_max_size` | `210M` | must be a bit **larger** than `upload_max_filesize` |
| `memory_limit` | `512M` (min `256M`) | large PDFs, image compression |
| `max_execution_time` | `120` | compressing big files |
| `max_input_time` | `120` | slow uploads |

If your plan won't go as high as 200M, use the highest it allows. **PDFNova automatically caps
uploads at PHP's limit** — nothing breaks, but then also lower the plans' "max file size" in
*Admin → Settings → Plans* so the Pricing page doesn't promise more than the server accepts.

---

## 3. Create the database and database user

**hPanel → Databases → Management** (called *MySQL Databases* on some accounts)

In "Create a new MySQL database and database user":

1. **Database name:** `pdfnova` → Hostinger adds a prefix, e.g. `u123456789_pdfnova`.
2. **Username:** `pdfnova` → becomes e.g. `u123456789_pdfnova`.
3. **Password:** click *Generate*, or use a long random password. **Copy it somewhere safe now.**
4. Click **Create**.

Write down the **full** names with the prefix — you need them in step 6:

```
Database: u123456789_pdfnova
User:     u123456789_pdfnova
Password: (the one you generated)
Host:     localhost
```

The user created this way has full rights on that database only, which is what PDFNova needs.

---

## 4. Import the database tables

1. In the same *Databases → Management* page, find your database in the list and click
   **Enter phpMyAdmin** (or *hPanel → Databases → phpMyAdmin*).
2. Click your database name in the **left sidebar** (e.g. `u123456789_pdfnova`).
3. Open the **Import** tab → **Choose file** → select `public_html/database/schema.sql`
   from the project on your computer → click **Import** (or **Go**) at the bottom.
4. You should see a green success message and **15 tables** in the sidebar
   (`users`, `files`, `processing_jobs`, `plans`, `settings`, …).

The file is safe to import again — it never deletes data. It does **not** create any user
accounts (a default admin password would be a security hole); you create your admin in step 11.

---

## 5. Upload the files to `public_html`

PDFNova must be installed at the **root of a domain or subdomain** (e.g. `https://yourdomain.com`
or `https://pdf.yourdomain.com`), **not** in a sub-folder like `yourdomain.com/pdfnova`.

**hPanel → Files → File Manager** → open `domains/yourdomain.com/public_html`
(on some accounts just `public_html`).

1. **Delete Hostinger's placeholder files** in `public_html` (usually `default.php`; also an
   old `index.html` if present). If there's an existing site there, back it up first.
2. Click **Upload** → choose `pdfnova-hostinger.zip` → wait until it finishes.
3. Right-click the zip → **Extract** → extract into the **current folder** (`public_html`, not a new sub-folder).
4. Delete `pdfnova-hostinger.zip` afterwards.

You should now see, directly inside `public_html`:
`index.php`, `.htaccess`, `admin/`, `api/`, `assets/`, `cron/`, `database/`, `includes/`,
`pages/`, `services/`, `storage/`, `templates/`, `vendor/`.

> If you see a single `public_html/public_html/` folder or `public_html/pdfnova-hostinger/`,
> the zip was extracted one level too deep — move the contents up one level.

If you open your domain now you'll see: *"PDFNova is not configured yet"*. That's expected.

---

## 6. Create `config.php`

1. In File Manager open `public_html/includes/`.
2. Right-click `config.example.php` → **Copy** → name the copy **`config.php`** (same folder).
3. Right-click `config.php` → **Edit**. Change these values:

```php
'app' => [
    'name'  => 'PDFNova',
    'env'   => 'production',              // must be 'production' on the live site
    'debug' => false,                     // NEVER true on the live site
    'url'   => 'https://yourdomain.com',  // your real address, https, no trailing slash
    'key'   => 'paste-64-hex-characters-here',
],

'db' => [
    'host'    => 'localhost',
    'port'    => 3306,
    'name'    => 'u123456789_pdfnova',    // from step 3, with prefix
    'user'    => 'u123456789_pdfnova',    // from step 3, with prefix
    'pass'    => 'your-database-password',
    'socket'  => '',                      // leave empty on Hostinger
    'charset' => 'utf8mb4',
],

'mail' => [
    'from_email' => 'no-reply@yourdomain.com',  // mailbox you create in step 10
    'from_name'  => 'PDFNova',
],

'legal' => [
    'company' => 'Your Company Ltd.',     // shown in Privacy Policy and Terms
    'address' => 'Street, City, Postcode',
    'country' => 'Your country',          // governing law in the Terms
],

'contact' => [
    'email' => 'support@yourdomain.com',
],
```

**The app key** signs download links and tokens. It must be **64 random hex characters**,
unique to your site. Generate it on your computer with either:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

```bash
openssl rand -hex 32
```

Paste the output as `'key'`. Don't reuse the key from your local copy, and don't share it.
(If you ever change it, existing download links and password-reset links stop working — that's all.)

Leave the other sections (`converter`, `ocr`, `payment`, `engines`) at their defaults for now:
- `engines`: Ghostscript/qpdf are normally **not available** on shared hosting. PDFNova works
  without them (pure-PHP compression).
- `converter` / `ocr`: the PDF⇄Office and OCR tools stay marked "not available" until you add an
  API key — see `docs/CONVERTERS_AND_OCR.md`.
- `payment`: online payment is **not implemented yet** (see `docs/PAYMENTS.md`). Paid plans are
  assigned manually in *Admin → Users*.

4. **Save** and close.

Now open `https://yourdomain.com/pricing` (or `http://` if SSL isn't on yet) — it should show
the three plans. **The pricing page reads from the database, so it's a good test** (the homepage
loads even without a database). If it shows *"Something went wrong"*, the database settings are
wrong: open `storage/logs/app.log` in File Manager — a line *"Database connection failed"* confirms
it. Re-check the database name, user and password (with the `u123456789_` prefix).
For safety, visitors never see the technical reason — only the log does.

---

## 7. Folder permissions

Hostinger runs PHP as **your own account user**, so the default permissions usually just work.
Check in File Manager (right-click → *Permissions*):

| Path | Permission |
|---|---|
| All folders | `755` |
| All files | `644` |
| `includes/config.php` | `600` (recommended) |
| `storage/`, `storage/uploads`, `storage/processed`, `storage/temp`, `storage/logs` | `755` — must be writable by PHP |

Never use `777` — it's not needed on Hostinger and is unsafe.

> `config.php` at `600` means only your account can read it. If the site then shows an error,
> set it back to `644` — it's still protected from the web by `includes/.htaccess`.

The System check (step 13) confirms the storage folders are writable.

---

## 8. The `.htaccess` files

PDFNova relies on `.htaccess` files for pretty URLs and protection. Hostinger's web server
(LiteSpeed) reads them like Apache. They're inside the zip — just make sure they arrived.

In File Manager, turn on **Show hidden files** (settings/gear icon), then check these exist:

| File | What it does |
|---|---|
| `public_html/.htaccess` | pretty URLs, HTTPS redirect, security headers, blocks private folders, compression and caching |
| `storage/.htaccess` | blocks web access to uploaded files and stops scripts running there |
| `includes/`, `database/`, `cron/`, `pages/`, `templates/`, `services/`, `vendor/`, `admin/pages/`, `admin/partials/` — each has `.htaccess` | deny all web access |
| `assets/.htaccess` | assets are served, but scripts never run there |

**Don't edit them** unless you know why. Two optional tweaks in `public_html/.htaccess`:

- **www → non-www redirect:** un-comment the two `www` lines near the top.
- **Upload limits:** if hPanel's PHP options don't apply for some reason, the commented
  `php_value` block at the bottom can be enabled. Use one or the other, not both.

---

## 9. SSL (HTTPS)

**hPanel → Security → SSL** → find your domain → **Install** (free Let's Encrypt certificate).
It can take a few minutes up to a few hours to become active.

You don't need Hostinger's "Force HTTPS" switch — PDFNova's `.htaccess` already redirects
`http://` to `https://`. (Turning both on is harmless.)

Once HTTPS works, make sure `app.url` in `config.php` starts with `https://`.

> **Important — HSTS.** Over HTTPS, PDFNova tells browsers to use HTTPS for **one year**,
> **including subdomains** (`Strict-Transport-Security: max-age=31536000; includeSubDomains`).
> Before going live, make sure every subdomain you use (e.g. `mail.`, `shop.`) also has SSL, or
> browsers that visited PDFNova will refuse to open them over plain HTTP. If that's a problem,
> remove `; includeSubDomains` in `includes/security.php`.

If your domain uses **Cloudflare** in front of Hostinger: set Cloudflare SSL mode to
**Full (strict)** (not *Flexible*, which causes redirect loops), and only then set
`'trust_proxy_headers' => true` in `config.php` so visitor IP addresses are read correctly.

---

## 10. The sender mailbox (emails)

PDFNova sends password-reset emails and contact-form notifications with PHP `mail()` through
Hostinger's mail server. Emails are only delivered reliably when the sender is a **real mailbox
on your own domain**.

1. **hPanel → Emails → Email Accounts** → create `no-reply@yourdomain.com`
   (set a strong password; you don't need to use the mailbox).
2. Make sure `mail.from_email` in `config.php` is exactly that address.
3. If your domain's DNS is managed by Hostinger, SPF and DKIM records are added automatically.
   If DNS is elsewhere (e.g. Cloudflare), copy the SPF/DKIM records shown in
   *hPanel → Emails → (your domain) → DNS settings* to your DNS provider.

Test it in step 14 with "Forgot password".

---

## 11. Create your admin account

There is no default admin (on purpose). Create one like this:

1. Open `https://yourdomain.com/register` and register with **your** email and a strong password.
2. In **phpMyAdmin** → your database → **SQL** tab, run (with your email):

   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'you@yourdomain.com';
   ```

   It should say *1 row affected*.
3. Reload the site — an **Admin** link appears in the top bar, and
   `https://yourdomain.com/admin/` opens the admin panel.

Then, in **Admin → Settings**:
- set **Contact email** (where contact-form messages go),
- review guest limits (max file size, daily jobs) and plan limits,
- leave *maintenance mode* off.

---

## 12. The cleanup cron job

Uploaded files are deleted right after processing, and results after 2 hours — **the cron job
does that deletion**, plus clearing old records. Without it, files pile up on your disk.

**hPanel → Advanced → Cron Jobs** → choose **Custom** → command:

```
/opt/alt/php83/usr/bin/php /home/u123456789/domains/yourdomain.com/public_html/cron/cleanup.php
```

- Replace `u123456789` and `yourdomain.com` with yours. Your exact home path is shown in
  File Manager's path bar or in *hPanel → Advanced → SSH Access*.
- `php83` should match the version you chose in step 2 (`php82` for 8.2). If that path doesn't
  exist on your server, use `/usr/bin/php` — but then confirm it's PHP 8.1+, because it can be an
  older default version (via SSH: `/usr/bin/php -v`).
- **Schedule:** every 15 minutes — minute `*/15`, every hour, every day, every month, every weekday
  (i.e. `*/15 * * * *`).

Save. After 15 minutes, open the cron job's **View output**: you should see a line like

```
[2026-11-13T06:37:13+00:00] cleanup: 0 files, 0 orphans, 0 stuck jobs, 0 rate-limit rows, 0 resets, 0 old records (0.01s)
```

The System check also shows when cleanup last ran. The script can't be run from a browser
(it's blocked and refuses web requests).

---

## 13. Run the System check

Open **`https://yourdomain.com/admin/`**. The **System check** panel tests your live setup on
every visit — using the same PHP the website uses, so its results are the ones that count:

PHP version, extensions, PDF libraries, upload/memory/time limits, production mode, debug off,
app key, site URL, HTTPS, writable folders, storage protection, disk space, cron last run,
database schema, email sender, contact email and legal details.

- **Fix** (red): something is broken or unsafe — fix it before going live.
- **Warning** (amber): works, but should be fixed (e.g. low upload limit, cron not run yet).
- Aim for all **OK**. "Cleanup cron job" turns OK after the first run.

---

## 14. Test everything

Do these on the live site, ideally in a normal and a private (incognito) window.

### Pages and protection

Open each URL — the expected result is in the right column:

| Open | Expect |
|---|---|
| `http://yourdomain.com` | redirected to `https://` |
| `https://yourdomain.com/merge-pdf` | the tool page (pretty URL works) |
| `https://yourdomain.com/no-such-page` | PDFNova's own 404 page |
| `https://yourdomain.com/includes/config.php` | **404** (never the file, never a blank page) |
| `https://yourdomain.com/storage` | **404** |
| `https://yourdomain.com/database/schema.sql` | **404** |
| `https://yourdomain.com/cron/cleanup.php` | **404** |
| `https://yourdomain.com/vendor/autoload.php` | **404** |
| `https://yourdomain.com/robots.txt` | rules incl. `Sitemap: https://yourdomain.com/sitemap.xml` |
| `https://yourdomain.com/sitemap.xml` | list of page URLs on your domain |

Or from a terminal (should print 404 for each):

```bash
for p in includes/config.php storage database/schema.sql cron/cleanup.php vendor/autoload.php; do curl -s -o /dev/null -w "%{http_code}  /$p\n" "https://yourdomain.com/$p"; done
```

Security headers (should list `strict-transport-security`, `content-security-policy`,
`x-frame-options`, `x-content-type-options`):

```bash
curl -sI https://yourdomain.com/ | grep -iE 'strict-transport|content-security|x-frame|x-content-type'
```

> If `robots.txt` shows `Disallow: /` for everything, `app.env` is not `production`.

### Uploads and processing

| Test | Expect |
|---|---|
| Merge PDF: 2–3 PDFs → Merge | one PDF with all pages in your order; download works |
| Split PDF: a multi-page PDF → by range / every page | correct pages; ZIP download for many files |
| Compress PDF: a PDF with photos | "Your PDF is X% smaller" (a PDF that's already compact says "already well optimized" — that's normal) |
| Rotate / Organize | pages turned / reordered correctly |
| Watermark | text visible on chosen pages |
| Protect → then Unlock the result with the same password | asks for password / opens without one |
| JPG to PDF: 2 photos | one PDF, one photo per page |
| PDF to JPG | images created in your browser (no upload) |
| Upload a `.txt` renamed to `.pdf` | rejected: not a real PDF |
| Upload a file larger than your limit | clear "file too large" message |
| Copy a download link → open it in the **private window** | refused (link only works for the session that created it) |
| A Word/Excel/OCR tool (with no API key) | clearly marked "not available yet", no fake upload |

After testing, *Admin → Processing jobs* lists the jobs; *Admin → Files* shows the stored files,
and after the cron runs, expired ones are gone.

### Accounts

| Test | Expect |
|---|---|
| Register a second test account | lands on its dashboard |
| Log out → log in | works; wrong password shows a generic error |
| More than 6 wrong passwords for one email within 15 minutes | temporarily blocked ("too many attempts") |
| Forgot password → email arrives → link sets a new password | link works **once**; other devices are signed out |
| Account → Settings → change password / sign out other devices | works |
| Contact form | message appears in *Admin → Contact messages* and an email reaches your contact address |
| Account → Delete account (test account) | account removed, can't log in |
| Open `/admin/` as the normal test user | "Access denied" page |

Finally, delete your test accounts in *Admin → Users*.

---

## Going live — final checklist

- [ ] System check: no red items; warnings understood
- [ ] `app.env` = `production`, `app.debug` = `false`, `app.url` = `https://…`
- [ ] Unique 64-character `app.key`
- [ ] SSL active, `http://` redirects to `https://`, subdomains have SSL (HSTS)
- [ ] Cron job runs every 15 minutes (System check shows a recent run)
- [ ] Sender mailbox exists; password-reset email arrives (check spam)
- [ ] Contact email set in Admin → Settings
- [ ] `legal.company`, `legal.address`, `legal.country` filled in, and the Privacy Policy,
      Terms and Cookie Notice **reviewed by someone qualified for your country**
      (they describe how PDFNova really works, but they're not legal advice)
- [ ] Plan limits match your PHP upload limit
- [ ] Test accounts deleted
- [ ] Hostinger backups enabled (below)

Optional: submit `https://yourdomain.com/sitemap.xml` in Google Search Console.

---

## Backups

- **Hostinger backups:** *hPanel → Files → Backups* (frequency depends on your plan).
- **Your own copy** (recommended monthly): phpMyAdmin → your database → **Export** → Quick → SQL;
  and download `includes/config.php`. Uploaded files are temporary by design, so the database
  and `config.php` are all you need — the rest is in the project folder.

---

## Updating PDFNova later

1. On your computer, get the new version and run `bash build/package.sh`.
2. *Admin → Settings* → turn **maintenance mode** on (visitors see a "back soon" page; admins still have access).
3. Upload the new zip to `public_html` and **extract, overwriting** existing files.
   Your `includes/config.php` and `storage/` contents are untouched (they're not in the zip).
4. If the update includes new files in `database/migrations/`, run each one in phpMyAdmin
   (SQL tab or Import), oldest first. The update notes will say so.
5. Check the System check, then turn maintenance mode off.

---

## Troubleshooting

| Problem | Likely cause and fix |
|---|---|
| **"PDFNova is not configured yet"** | `includes/config.php` is missing or misnamed (e.g. `config.php.txt`, or still `config.example.php`). |
| **"Something went wrong" on most pages** (homepage still loads) and `storage/logs/app.log` says *Database connection failed* | Wrong database name/user/password in `config.php`. Use the full prefixed names; host `localhost`; leave `socket` empty. |
| **500 Internal Server Error** | Look in *hPanel → Advanced → Error logs* (or `storage/logs/app.log` via File Manager). Common causes: PHP below 8.1; `vendor/` missing (the package wasn't built with step 1); a typo in `config.php` (a missing comma or quote). |
| **Every page except the homepage is 404** | `public_html/.htaccess` is missing — re-upload it (enable "Show hidden files"). Or the site is in a sub-folder, which isn't supported. |
| **Redirect loop** | Cloudflare SSL set to *Flexible* — change to *Full (strict)*. |
| **Upload fails / "file too large" at a small size** | Raise `upload_max_filesize` and `post_max_size` (step 2). The System check shows the current values. |
| **Big files time out when compressing** | Raise `max_execution_time` and `memory_limit` (step 2). |
| **Password-reset emails don't arrive** | Check spam; `mail.from_email` must be an existing mailbox on your domain (step 10); check SPF/DKIM. |
| **Files are not deleted** / storage keeps growing | The cron job isn't running — check its command path and *View output* (step 12). |
| **"Session expired, please reload" on forms** | Cookies blocked in the browser, or a CDN/cache is caching HTML. PDFNova sends `Cache-Control: no-store` on pages; don't add "cache everything" rules in Cloudflare or Hostinger CDN for this site. |
| **Search engines show nothing** | `app.env` isn't `production` (robots.txt then blocks all crawlers on purpose). |
| **Locked out of admin** | In phpMyAdmin run the `UPDATE users SET role = 'admin' …` query again for your email; use "Forgot password" if needed. |

---

## What runs where — honest limits on shared hosting

- **Works on Hostinger as-is:** Merge, Split, Compress (pure PHP), Rotate, Organize, Watermark,
  Protect, Unlock, JPG to PDF (server); PDF to JPG and page previews (in the browser); accounts,
  dashboard, admin, contact form, cleanup.
- **Needs an external API key** (not included): Word/Excel/PowerPoint ⇄ PDF (CloudConvert) and
  OCR (OCR.space). Until configured, these tools are clearly marked as not available.
  Using them sends the user's file to that provider — the Privacy Policy then lists it automatically.
- **Not available on shared hosting:** LibreOffice, Ghostscript, qpdf and similar server programs.
  Compression without Ghostscript works but is usually less strong on PDFs without images.
- **Online payments:** not implemented. Upgrade users manually in *Admin → Users*.
- **Tested before release:** the full site on PHP 8.5 with MariaDB, and under Apache with these
  exact `.htaccess` files (pretty URLs, blocked folders, no script execution in uploads, full
  upload → process → download). It has **not** been tested on Hostinger's own servers — that's
  what step 13 and step 14 are for.
- The `Server` response header (web server name) is set by Hostinger and can't be changed by the site.
