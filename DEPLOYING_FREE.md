# Free hosting setup

This project can run on a free PHP/MySQL host. The steps below use [InfinityFree](https://www.infinityfree.com/), which currently advertises PHP 8.4, MySQL 8.0/MariaDB 11.4, HTTPS certificates, free subdomains, and FTP/file-manager uploads. The project requires PHP 8.2+, PDO MySQL, and mbstring.

Free shared hosting is suitable for trying the site and a small launch. Keep an exported database backup and do not treat a free account as a guaranteed production service.

## 1. Create the hosting account

Create your own free account and choose a free subdomain. The provider offers no-cost subdomains and says a credit card is not required. Do not share your hosting password or database password in chat.

## 2. Create the hosted database

In the hosting control panel, create a MySQL database and copy its hostname, database name, username, and password. The database hostname is provider-specific; do not assume it is `localhost`.

In the project, copy `app/config.example.php` to `app/config.local.php` and enter those values. Upload `config.local.php` only to your own hosting account. It is ignored by source control. Environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) still take precedence if the host supports setting them.

## 3. Upload the project

Upload the project files and folders into the hosting account's `htdocs` directory, including the root `.htaccess`, `app`, `database`, `public`, and `scripts` directories. The root `.htaccess` forwards public requests to `public/`; keep the `app` directory in place because the PHP pages load code from it.

## 4. Import the schema

Open the hosted database in phpMyAdmin, select it, and import `database/hosted-schema.sql`. This hosted version does not try to create a database; it creates the tables inside the database you selected. It includes the current example workspaces and membership packages, plus role-based admin account fields.

## 5. Create the hosted admin account

On your own computer, open a private terminal in the project and run:

```powershell
& 'C:\xampp\php\php.exe' scripts/create-admin-sql.php
```

Use a new, unique password of at least 12 characters. The script asks for the super admin's name and prints SQL containing only the password hash. Copy that SQL into the hosted database's phpMyAdmin SQL tab and run it once. The SQL only inserts an account when the admin table is empty. Keep the password private.

## 6. Open the site

Visit the free subdomain and sign in at `/admin/login.php`. Use the host control panel to enable its HTTPS certificate, then check the public booking form, admin package editor, Customers page, and **Admin users** page over HTTPS. The first account is a super admin and can create staff admins; staff admins cannot manage administrator accounts.

Before accepting real booking enquiries, replace the example space details, prices, contact information, and policies with confirmed business information. Export the hosted database periodically from phpMyAdmin and store the backup somewhere private.
