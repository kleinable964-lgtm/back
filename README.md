# CRM API

Laravel 13 API with Sanctum, MySQL for local development, and PHPUnit.

This is an independent repository: https://github.com/kleinable964-lgtm/back. The React frontend runs separately. No Node.js installation or files from the parent workspace are required.

## First-time setup

Requires PHP 8.3+, Composer, Laravel's required PHP extensions, PDO MySQL, and MySQL Server. Tests also use PDO SQLite.

From this repository's root in PowerShell, after cloning:

```powershell
composer install
Copy-Item .env.example .env
.\php-local.cmd artisan key:generate
```

Create a MySQL database named `crm` and an application user named `crm_app` with privileges on that database. Set the user's password in `.env`; `.env.example` deliberately contains no password. The default host is `127.0.0.1` and port is `3306`.

```powershell
php artisan config:clear
php artisan migrate
```

The current laptop is already configured. Do not overwrite an existing `.env` or database.

The Windows `php-local.cmd` wrapper enables the installed SQLite extensions for tests. MySQL is already enabled in this laptop's PHP configuration. On other platforms, enable PDO SQLite for tests and PDO MySQL for development, and use `php` instead of the wrapper.

## Development

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

- `GET /api/health`: service health.
- `GET /api/user`: current authenticated user; requires Sanctum authentication.
- `GET /up`: Laravel health endpoint.

Application routes live in `routes/api.php`. Controllers live in `app/Http/Controllers`. There is no Blade welcome page or backend frontend build.

## Frontend API

Start the backend with `php artisan serve --host=127.0.0.1 --port=8000`, then open **http://127.0.0.1:8000/swagger**. This is the built-in Swagger UI: expand endpoints, edit requests, click **Try it out → Execute**, and inspect responses. Log in, copy access_token, then paste the token into **Authorize** to call protected endpoints.

Documentation is generated from routes, Form Requests and resources; there is no handwritten OpenAPI JSON file to maintain. Swagger UI assets are installed with Composer and served locally. Its generated specification remains available at `GET /api/openapi.json`. New endpoints use `/api/v1`; existing health and current-user routes remain compatible.

The documentation generator inspects the configured MySQL schema. Configure valid database credentials in `.env`, run `php artisan config:clear`, and apply migrations with `php artisan migrate` before using it. Viewing documentation does not run migrations or switch databases. SQLite is used only by the isolated test suite, via the PHP wrapper. Documentation is available in the local environment; other environments require an explicit `viewApiDocs` gate.

1. Public self-registration is disabled. Coworker accounts are created by administrators through `POST /api/v1/users`; the frontend admin dashboard is a future feature.
2. Bootstrap your first administrator through trusted server access:

   ```powershell
   php artisan crm:create-admin you@example.com --name="Your Name"
   ```

   The command prompts privately for a password and confirmation. Passwords require 12–72 characters, at least one uppercase letter, lowercase letter and number, and cannot exceed 72 bytes. Existing accounts are never overwritten. Use `crm:approve-user email@example.com` to activate an existing account, adding `--admin` only when administrative access is intended.
3. Log in with `POST /api/v1/auth/login` using email/password and optional device_name. Use the returned access_token in `Authorization: Bearer <access_token>`. Tokens expire after 12 hours. Send `Accept: application/json` and `Content-Type: application/json`.
4. Read your identity/desks with `GET /api/v1/auth/me`. `POST /api/v1/auth/logout` revokes the current token.

| Endpoints under /api/v1 | Access |
|---|---|
| `users` | Active coworkers list/read; admins create/update. Disable accounts rather than deleting them. |
| `desks`, `brands` | Active coworkers list/read; admins create/update/delete. |
| `clients` | Admins manage all clients; other active coworkers read only clients assigned to them. |
| `PUT desks/{desk}/users` | Admins replace memberships with `{"user_ids":["coworker-uuid"]}`. Empty array clears membership. |
| `departments` | Active coworkers list/read; admins create/update/delete and manage desk attachments. |
| `customer-statuses` | Active coworkers list/read; admins create/update/delete. |
| `roles` | Active coworkers read all three supported role definitions. No role write endpoints. |
| `countries`, `affiliates` | Active coworkers read existing reference records. No write endpoints yet. |

Managers currently have the same API access as agents. Desk membership does not automatically grant permissions or determine client assignment eligibility. Both PUT and PATCH accept partial updates. Domains must be unique hostnames without a protocol/path/port.

The `roles` table contains exactly the supported definitions `admin`, `manager` and `agent`, with display labels. `name` is the primary key; `users.role` references that name, so existing API payloads continue to use strings rather than `role_id`. Each coworker has at most one role. Pending/unassigned coworkers may retain null until an administrator assigns a role. The migration creates the three definitions automatically and preserves accounts, passwords, tokens, timestamps, desk memberships and client assignments. Unsupported legacy role values stop the migration before schema changes so they can be resolved explicitly.

Use `GET /api/v1/roles` to populate future role dropdowns. It returns all three definitions in a `data` array as `{ "name": "manager", "label": "Manager" }` records. Assign a role using the existing administrator user create/update endpoints, for example `PATCH /api/v1/users/{uuid}` with `{ "role": "manager" }`. The current role-change token revocation still applies. Role definitions are fixed reference data, separate from permission groups. `php artisan db:seed --class=RoleSeeder` safely restores missing definitions and updates their labels without reassigning users; regular `db:seed` includes this seeder.

For future authorization, `User::hasRole()` accepts one or more role names:

```php
$user->hasRole('admin');
$user->hasRole('admin', 'manager');
$user->roleDefinition?->label;
```

Add future admin-only API routes inside the existing `can:manage-crm` route group in `routes/api.php`. For an action shared by admins and managers, define a gate in `AppServiceProvider::boot()` and apply its `can:` middleware to the new route. For example, when implementing a reports feature:

```php
Gate::define('view-reports', fn (User $user): bool =>
    $user->status === 'active' && $user->hasRole('admin', 'manager')
);
```

Protect that feature's API route with `->middleware('can:view-reports')` inside the authenticated active-user group. This is an example gate, not an existing reports endpoint. React should guard the corresponding page and navigation too; existing admin pages use `RequireAdmin` in `crm-front/src/app/route-elements.tsx`. Backend authorization remains authoritative. Adding a role record alone never grants access to a route.

Desks have no default flag or automatic coworker assignment. Without explicit `desk_ids`, new coworkers start without desk memberships; administrators can also assign memberships through the dedicated membership endpoint. The desk flag removal migration preserves desks and existing memberships.

User create/update now also accepts `permission_group` (nullable: `admin`, `conversion-agent`, `retention-agent`, `conversion-tl`, `retention-tl`) and `desk_ids` (up to 500 distinct existing desk UUIDs). Groups are metadata and do not grant permissions; access continues to use `role`. User and desk writes are transactional. Omitted `desk_ids` preserves memberships; `[]` clears them. On update, an omitted or empty password preserves its current hash. A non-empty replacement must meet the existing password policy and include matching `password_confirmation`. Creation still requires a password; the frontend explicitly sends `status: active` for accounts created through its admin form.

User resources include `permission_group`, `desk_count` and nullable `last_login_at`. Successful login records the login time without changing the account-edit timestamp; failed login leaves it unchanged. Existing users retain null group/login values until updated or signed in. Passwords are never returned. Changing role, status, password or permission group revokes existing tokens. Apply the management-fields migration before using these endpoints.


`PATCH /api/v1/auth/preferences` saves a required fixed UTC offset from `config/timezones.php`, such as `UTC-05:00`, `UTC+05:45` or `UTC` for zero. Options are unique and cover UTC-12:00 through UTC+14:00 with fractional offsets. Preferences apply only to the signed-in active coworker and cannot change identity, role or another account. User resources, including login and auth/me, return the saved value. Fixed offsets do not change for daylight saving. Existing IANA preferences remain readable until the user saves a fixed offset. No schema migration is required for this change; existing timestamps remain unchanged.

Department endpoints are GET/POST `/api/v1/departments` and GET/PUT/PATCH/DELETE `/api/v1/departments/{department}`, using UUIDs. Responses contain `id`, `name`, nullable `description`, boolean `isactive`, nullable ISO timestamps `created_at` and `updated_at`, and `attached_desks: [{id, name}]` inside the usual data wrapper.

Create/update accepts `name` (required on creation, maximum 255 characters), nullable `description` (maximum 65,535 characters), optional `isactive` (defaults true), and optional `desk_ids` (up to 500 distinct existing desk UUIDs). `attached_desks` is read-only. Omitting `desk_ids` on update preserves attachments; supplying it replaces them, and `[]` detaches all. A desk can belong to only one department, and attachments cannot silently take desks from another department; explicitly reassign through the desk endpoint first. Writes are atomic. Departments with attached desks return 409 on deletion. Deactivating a department does not change its desks' activity flags.

Run migrations to add department timestamps. Existing departments retain null dates because their original creation/update times were not stored. New departments record both dates; later changes, including desk attachment updates, record `updated_at` without inventing a legacy creation date.

The additive department migration preserves existing IDs and desk links. Legacy identity-only departments may have a null name until an administrator provides one; new departments require a name. Country and affiliate endpoints remain read-only.

Customer-status endpoints are GET/POST `/api/v1/customer-statuses` and GET/PUT/PATCH/DELETE `/api/v1/customer-statuses/{customerStatus}`, using UUIDs. Responses contain only `id`, `name`, `color`, `is_default`, `disabled_for`, `created_at` and `updated_at` inside the usual `data` wrapper. Timestamps use ISO UTC strings. Existing FTD and order-weight columns are preserved internally.

Create a status with:

```json
{
  "name": "Qualified",
  "color": "#22c55e",
  "is_default": false,
  "disabled_for": ["retention"]
}
```

`name` and `color` are required on creation. Names accept up to 255 characters. Colors require `#` followed by exactly six hexadecimal digits and are normalized to lowercase. `is_default` is optional (false by default). `disabled_for` is an optional list (empty by default) containing distinct `conversion` and/or `retention` values; use the spelling `retention`. An empty list enables both workflows. PUT/PATCH preserve omitted fields; supplying `disabled_for` replaces the list. IDs and timestamps are read-only. Validation returns the usual 422 field errors.

Setting `is_default: true` clears the previous default atomically; a database constraint prevents two defaults, including concurrent writes. Having no default is allowed. This flag does not change existing customer assignments or automatically assign a status when creating a client. Statuses still assigned to clients return 409 on deletion; unused statuses return 204.

Lists use the existing pagination parameters. Without a filter they include all statuses for management. Add `?available_for=conversion` or `?available_for=retention` to get statuses not disabled for that workflow. These are workflow selection settings, not permission groups or department IDs; no department meaning is inferred from its name. Existing client writes are not restricted based on these settings.

Run `php artisan migrate` before using status management or Swagger. The additive migration preserves status IDs, colors, timestamps and customer links, adds workflow restrictions, and enforces a single default. If legacy records contain several defaults, it keeps the oldest (UUID breaks timestamp ties) and clears the other default flags without rewriting their timestamps. Legacy statuses without workflow restrictions are returned with `disabled_for: []`; historical nullable colors remain readable, while new color inputs must pass hex validation. Swagger generates request fields, workflow choices and the color pattern from the new request/resource classes.

Coworker IDs in v1 routes and assignment/membership bodies are public UUIDs; internal integer user IDs are never accepted there. Client and affiliate IDs are integers. Single records use `{"data": {...}}`; lists use `{"data": [...], "links": {...}, "meta": {...}}`. Lists accept page and per_page (1–100, default 25). Created records return 201; successful deletions/logout return 204.

Validation errors return 422 with `{"message":"...", "errors":{"field":["..."]}}`. Missing authentication returns 401, inactive accounts/insufficient permissions 403, missing or inaccessible records 404, referenced-record deletions 409, and rate limits 429. Login allows five requests per minute per IP; active protected routes allow 120 per minute per user. Changing a coworker's role, status or password revokes their existing tokens.

For the existing Vite frontend, use its `/api` development proxy. Production should expose API and frontend through the same origin or configure CORS for the intended frontend origin. No browser cookie authentication setup is included in this bearer-token contract.

Edit `.env` to configure a different database or other services. Commit `.env.example` and `composer.lock`; Git excludes local secrets, SQLite databases, logs, and dependencies.

## Checks

```powershell
.\php-local.cmd artisan test --compact
php vendor/bin/pint --dirty --format agent
```

Tests use an isolated, in-memory database.
