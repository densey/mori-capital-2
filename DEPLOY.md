# Mori Capital — Deployment Guide (CyberPanel + OpenLiteSpeed)

Production deployment on a Linux VPS running CyberPanel.
Stack: PHP 8.2+ · MariaDB 10.5+ / MySQL 8 · OpenLiteSpeed.

---

## 1. Create the website in CyberPanel

1. **CyberPanel → Websites → Create Website**
   - Domain: `mori-capital.com` (or your staging domain)
   - PHP version: **PHP 8.2** (or newer)
   - SSL: tick "Issue SSL" (Let's Encrypt)
   - DKIM, Open Basedir: defaults are fine

2. **CyberPanel → Databases → Create Database**
   - Database name: `mori_capital`
   - DB user: `mori`
   - Password: generate a strong one — save it for step 3
   - Privileges: ALL on this DB only

3. **CyberPanel → File Manager** (or SSH)
   - Navigate to `/home/<domain>/public_html/`
   - Upload the project (or clone via SSH)

---

## 2. SSH deployment (recommended)

```bash
ssh root@your-vps
cd /home/<domain>/public_html
rm -rf *                       # if there's a placeholder index.html
git clone https://github.com/densey/mori-capital-2.git .
# or upload the project zip and unzip in place
```

Set ownership so PHP-FPM can read/write what it needs:

```bash
chown -R <domain-user>:<domain-user> /home/<domain>/public_html
find /home/<domain>/public_html -type d -exec chmod 755 {} \;
find /home/<domain>/public_html -type f -exec chmod 644 {} \;

# Writable folders for uploads / sessions
mkdir -p uploads/documents uploads/media
chmod -R 775 uploads
chown -R <domain-user>:<domain-user> uploads
```

---

## 3. Configure `.env`

```bash
cp .env.example .env
nano .env
```

Fill in:
```
APP_ENV=production
SITE_URL=https://mori-capital.com
DB_HOST=127.0.0.1
DB_NAME=mori_capital
DB_USER=mori
DB_PASS=<your-strong-password>
SMTP_HOST=...   # CyberPanel mail server or your SMTP relay
```

Make `.env` unreadable to others:
```bash
chmod 640 .env
```

---

## 4. Run the install wizard

Open in your browser:

```
https://mori-capital.com/install.php
```

Walk through:
1. **Config** — confirms `.env` is present
2. **Database** — tests PDO connection
3. **Schema** — runs `db/schema.sql` then `db/seed.sql`
4. **Admin user** — create your first `super_admin`

When complete, **delete the installer immediately**:
```bash
rm /home/<domain>/public_html/install.php
```

---

## 5. Verify the homepage

Visit `https://mori-capital.com/`. You should see:
- Investor gate (first visit)
- Cookie banner after acknowledging
- Live homepage with funds and team pulled from the DB

If anything looks off:
- Browser DevTools → Console for JS errors
- CyberPanel → Logs → PHP / error log
- `tail -f /home/<domain>/logs/<domain>.error_log`

---

## 6. Sign into the CMS

```
https://mori-capital.com/admin/login.php
```

Use the email + password you created in the installer.

You should land on the dashboard with stat cards.

---

## 7. Recommended hardening

### Force HTTPS
Once SSL is confirmed working, uncomment the HTTPS-redirect block in `.htaccess`
(and the HSTS header line).

### Disable PHP error display
Already off in `bootstrap.php` (`display_errors = 0`). Errors go to the
PHP error log only.

### Cron — clean expired sessions (optional)
Add to CyberPanel → Cron Jobs:
```cron
0 3 * * * /usr/bin/php /home/<domain>/public_html/db/cron-cleanup.php
```
(create that file later if you need it)

### Backups
CyberPanel → Backup → set a daily backup destination (local + remote SFTP/S3).

---

## 8. SMTP for contact form + newsletter (optional)

If you want email notifications when the contact form is submitted, configure
SMTP credentials in `.env` and wire up a mail sender. The current build saves
all messages to the `contact_messages` table — visible in **Admin → Messages**.
A separate SMTP integration (PHPMailer or Symfony Mailer) can be added later
without touching the existing form.

---

## 9. Where things live

```
/home/<domain>/public_html/
├── index.php, about.php, …      ← public pages (PHP)
├── admin/                        ← CMS panel
├── api/                          ← public AJAX endpoints
├── src/                          ← PHP classes (denied to web via .htaccess)
├── db/                           ← schema.sql, seed.sql (denied to web)
├── uploads/                      ← user uploads (PHP execution disabled)
├── assets/, css/, js/, images/   ← static assets
├── .env                          ← environment (not in git)
└── .htaccess                     ← rewrite + security headers
```

---

## 10. Updates

To deploy code updates later:

```bash
ssh root@your-vps
cd /home/<domain>/public_html
git pull origin main
# If you changed schema:
# Run any migration in db/migrations/ manually via CyberPanel → phpMyAdmin
```

---

## 11. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `Database connection failed` | `.env` credentials wrong, or DB doesn't exist |
| Blank page | PHP error — check `<domain>.error_log` |
| Uploads broken | `uploads/` not writable by PHP-FPM user |
| Pretty URLs not working | OpenLiteSpeed `.htaccess` support disabled → re-enable in CyberPanel → vHost Conf |
| `419 CSRF` errors | Session cookies not setting — usually missing HTTPS while cookies are flagged secure |
| Investor gate keeps showing | Browser cleared localStorage — expected; one-shot only per browser |
