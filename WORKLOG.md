# Work Log

This file tracks completed and planned work for COE Workgrid. Update it as project work is finished.

## Completed

### Application

- [x] Built a responsive public coworking website with workspace, membership, and booking request sections.
- [x] Added server-side booking form validation, CSRF protection, and prepared SQL inserts.
- [x] Added an admin sign-in page and booking dashboard with status filtering and updates.
- [x] Added a CLI setup command for creating the first admin account.
- [x] Added an admin Packages screen to create, edit, highlight, activate, or archive membership packages.
- [x] Connected public membership cards and the booking form to active packages stored in the database.
- [x] Added the provided logo image to the public header/footer and admin sign-in and navigation.
- [x] Prepared free shared-host deployment files, including private database config support, a hosted schema import, and first-admin SQL generation.
- [x] Improved public and admin typography, control sizing, card treatments, and keyboard focus visibility.
- [x] Applied the navy, professional blue, crisp white, and silver brand palette across public and admin styles.
- [x] Added a restrained champagne accent to the public highlight and admin navigation.
- [x] Added workspace photos for open coworking, dedicated desk, and private office cards.
- [x] Added the supplied hero video with a poster image and reduced-motion playback handling.
- [x] Applied the requested desktop/mobile typography scale to public and admin interfaces.

### Database and local setup — 2026-11-06

- [x] Imported `database/schema.sql` into the local XAMPP MariaDB server.
- [x] Created the `coe_workgrid` database and its `admin_users`, `bookings`, `membership_plans`, and `workspaces` tables.
- [x] Seeded the database with 3 workspaces and 3 membership plans.
- [x] Confirmed PHP can connect to the `coe_workgrid` database.
- [x] Applied the membership package management migration to the local database, including descriptions, benefits, and featured-plan settings.
- [x] Added the customer accounts table and applied its migration to the local database.
- [x] Added an admin Customers page to create accounts with hashed passwords and activate or deactivate them.
- [x] Added role-based admin permissions: admins manage bookings, packages, and customers; super admins can also create and deactivate administrator accounts.
- [x] Added the Admin users page with protected account management, active-status controls, and last-super-admin safeguards.
- [x] Created the initial admin account for `admin@gmail.com`. The password was shared in the setup conversation; store it securely and change it if it has been exposed.
- [x] Applied the admin roles migration locally and promoted `admin@gmail.com` to super admin.

## Planned

- [ ] Confirm workspace names, descriptions, capacities, and included amenities with the business.
- [ ] Confirm monthly plan names, prices, currency, tax treatment, and included benefits.
- [ ] Confirm public contact details, business address, location/time zone, and opening hours.
- [ ] Confirm booking, access, cancellation, rescheduling, refund, and privacy/consent policies.
- [ ] Build customer sign-in and a customer portal.
- [ ] Add capacity-aware booking approval workflows.
- [ ] Add payment processing and customer notifications.
- [ ] Add reporting.
- [ ] Prepare production deployment configuration and review production security settings.
- [ ] Create the hosting account, import the live database, upload the site, and verify the public and admin pages online.

## Notes

- The local database currently has no booking requests.
- Local database credentials are controlled by environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
- See `README.md` for project requirements and local run instructions.
