# SaaS administration foundation

## Start locally

The schema migration is additive and preserves existing tables. Run pending migrations with `php artisan migrate`.

Create the first platform administrator with:

```powershell
& "C:\php84\php.exe" artisan saas:create-admin
```

The command prompts for name, email, and a hidden password. It creates a new account only; it will not promote an existing user or install default credentials. The database seeder deliberately does not create an administrator or a default password.

Start Laravel and Vite in separate terminals, then open `/app/login`. Sign in, open **Administration**, create a plan, and create a clinic. Onboarding atomically creates the clinic, a new owner account, main branch, trial subscription, and audit event. No emails are sent automatically.

Use **Manage** to update the clinic name/timezone/status, add branches, provision new members, change membership roles/status, and change subscription entitlements. At least one active owner must remain. Plan definitions are immutable; create another plan and explicitly assign it when changing terms.

## Security boundaries

- First-party Sanctum session cookies and CSRF protection; passwords/tokens are not persisted in browser storage.
- Login throttling by email/IP and IP.
- Platform endpoints require the platform-admin flag and tenant policies. The flag is not mass assignable.
- Platform services intentionally use explicit tenant-qualified queries to manage organization metadata; there is no global clinical authorization bypass.
- Global users and tenants are identity/control-plane records. Branch, membership, and subscription models use a fail-closed TenantContext scope.
- Active clinic selection checks the current user's active membership and tenant status; every clinic request rechecks both and subscription access.
- Trial expiry is checked on requests, so it does not depend on a scheduled job.
- Tenant ownership fields are prohibited in input and assigned on model creation. Model ownership changes are denied.
- Raw SQL, bulk updates/inserts, jobs, and future imports must not assume model hooks apply. Explicitly constrain tenant ownership and restore/clear context for jobs.
- Provisioning, quotas, and subscription changes use transactions and lock the same tenant row to serialize changes. Plan downgrades below current usage are rejected.
- Clinic switching clears displayed branch data before loading the next clinic. Session expiry clears identity state and returns to login.
- Only metadata needed by each screen is returned through API Resources.
- Platform mutation audit events are appended within the same transaction. No audit modification/deletion endpoint exists.
- Tenant context is cleared in middleware finally blocks; future tenant route binding must execute after context resolution. Current branch resolution is explicit inside the controller.
- Use composite tenant foreign keys when future tenant-owned tables reference branches/memberships; these parents already have unique (tenant_id, id) keys.

## Implemented scope

Platform administrator bootstrap; login/logout/session; clinic selection; tenants; owner provisioning; branch/member management; fixed membership roles (owner/admin/staff); name/timezone settings; immutable plans with branch/member limits and trial days; manual trial/active/cancelled subscriptions; suspension; platform audit listing.

This does not implement payment collection, subscription invoicing, self-service signup/invitations, password recovery/MFA, configurable role-permission catalogs, healthcare modules, or tenant background jobs. Clinical permissions must be designed before those modules are added. The active subscription status represents access entitlement, not proof of payment. Audit events currently identify actions and subjects, not a complete before/after revision history.

## Verification

```powershell
& "C:\php84\php.exe" artisan test
npm run build
npx playwright install chromium
npm run test:browser
```

Browser tests reset only `storage/framework/testing/saas-browser.sqlite`, guarded by an exact database-path check. They start a separate server on port 8011. Test credentials exist only in this isolated fixture database. Set `PHP_EXECUTABLE` to use another PHP binary. Browser fixtures keep APP_ENV=local so real CSRF protection remains enabled.

PHPUnit defaults to in-memory SQLite. For database-engine verification, set DB_CONNECTION and DB_DATABASE in the shell to a dedicated, disposable MySQL/MariaDB test database before running PHPUnit. Never point RefreshDatabase tests at the development database.

The installed development server is MariaDB 10.4.32, accessed through Laravel's mysql driver. Verification on it does not certify MySQL compatibility or production readiness. Before deployment, use the intended supported database/runtime, HTTPS secure cookies, trusted Sanctum domains, APP_DEBUG=false, secret management, backups, and concurrency/load checks.
