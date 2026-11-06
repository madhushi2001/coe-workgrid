# COE Workgrid

The first implementation slice is a responsive public coworking website with a monthly booking-request form. Requests are validated on the server and saved as `pending` in MySQL; approval and payment are intentionally left for the admin/customer portal phases.

## Requirements

- PHP 8.2+
- PHP PDO MySQL extension
- PHP mbstring extension
- MySQL 8+

## Run locally

1. Import `database/schema.sql` into MySQL.
2. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` for your local database.
3. Create the first admin account with `php scripts/create-admin.php`. Run this command in a private terminal; PHP's readline prompt may display password input as you type. The command refuses to create another admin once one exists.
4. From the project root, run `php -S localhost:8000 -t public`.
5. Open `http://localhost:8000` for the public site or `http://localhost:8000/admin/login.php` for the admin panel.

For an existing database, apply each migration that has not yet been run: `database/migrations/20261106_membership_package_management.sql` adds admin-managed packages, `database/migrations/20261106_customer_accounts.sql` adds customer accounts, and `database/migrations/20261106_admin_roles.sql` enables role-based admin access. **Super admins** can open **Admin users** to create and deactivate admin accounts; **Admins** can manage bookings, packages, and customer accounts.

The landing page can render if the database is unavailable, but a database is required to load membership packages, submit booking requests, and use the admin panel. Database errors are logged and surfaced as an actionable message.

See `DEPLOYING_FREE.md` for steps to host this project on a free PHP/MySQL shared host. The production database can be configured with a private `app/config.local.php` file or the `DB_*` environment variables.

## Current scope

- Public landing page, workspace options, sample monthly plans, and responsive navigation.
- CSRF-protected booking request form with server-side input/date validation and prepared SQL.
- MySQL schema for initial workspaces, plans, and booking requests.
- Admin sign-in, booking dashboard with live summary counts, status filtering and status updates.
- Admin package management for membership names, monthly prices, descriptions, benefits, featured display, and active status.
- Admin customer account creation with password hashing and activation controls.
- Role-based admin authentication: admins manage bookings, packages, and customer accounts; super admins also manage admin accounts.
- CLI-only first-admin setup with password hashing, prepared statements, and a one-admin-only guard.

The workspace offerings, sample pricing, capacities, contact details, and business policies are placeholders; confirm them with the business before launch. Customer sign-in and portal, CMS, capacity-aware approval workflow, payments, notifications, and reporting remain planned follow-on work.
