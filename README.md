# Review Capture & Routing System

Vanilla PHP + MySQL review funnel for multi-family properties. No frameworks, no build step.

- **4–5 stars** → buttons to the property's public review pages (Google, Yelp, Apartments.com, ApartmentRatings, other).
- **1–3 stars** → private feedback form, saved to MySQL and emailed to the property's team.
- **Admin panel** → manage properties, per-property wording and links, and browse captured feedback.

## Requirements

PHP 7.4+ (PDO MySQL), MySQL 5.7+/MariaDB, Apache.

## Setup

1. Put the files in your web root (e.g. `C:\wamp64\www\finger_reviews`).
2. Edit `config/db.php` — database host/name/user/password and the `MAIL_FROM` address.
3. Open `http://localhost/finger_reviews/install.php`. It creates the tables and lets you create the first admin account.
   (Alternatively import `schema.sql` by hand.)
4. **Delete `install.php`.**
5. Sign in at `/admin/`, add a property, and share its review link (`review.php?property_id=X`).

## Email

Alerts use PHP `mail()`. WAMP does not send mail out of the box — point `SMTP`/`smtp_port`/`sendmail_from`
in `php.ini` at a mail relay locally. On typical Linux hosting `mail()` works as-is. Feedback is always saved
to the database even when the email fails (failures go to the PHP error log).

## Structure

```
config/db.php            PDO connection + settings
config/functions.php     Shared helpers (escaping, CSRF, validation)
admin/                   Admin panel (login, dashboard, properties, feedback, account)
assets/css/style.css     All styling
assets/js/main.js        Star selection + dynamic form logic
review.php               Public page (?property_id=X)
submit-review.php        Handler for 1–3 star submissions
schema.sql               Database creation script
install.php              One-time installer (delete after use)
```

## Security notes

PDO prepared statements everywhere, `htmlspecialchars()` on all output, bcrypt password hashes,
CSRF tokens on every form, session ID regenerated at login, honeypot on the public form,
`config/` and `.sql`/`.md` files blocked via `.htaccess`. Serve over HTTPS in production.
