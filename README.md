# OfficeStock - HSB2006 Production Architecture

OfficeStock is a database-driven business web application for office-supply inventory and internal stationery issuance. This package is designed for online assessment and follows the HSB2006 final-examination requirements: database, PHP connection, authentication, exactly three user roles, CRUD, one complete business workflow, search/filter/sort/reporting, validation, security controls, tests, GitHub history, setup instructions and a live application link.

## 1. Final architecture

```text
Browser
  |
  v
Vercel - static responsive frontend
  |
  | HTTPS JSON API
  v
PHP 8.x API - Docker/web hosting
  |
  | PDO prepared statements
  v
Online MySQL/MariaDB
```

The frontend contains no hard-coded product, inventory, request or dashboard records. Business data is requested from the PHP API.

## 2. Exactly three roles

| Role | Main permissions |
|---|---|
| `ADMIN_MANAGER` | Dashboard, Product/Supplier CRUD, inventory view, request approval/rejection, reports, users, transaction history |
| `WAREHOUSE` | Dashboard, product/inventory view, stock in/out, approved requests, request issue, transaction history |
| `EMPLOYEE` | Dashboard, stationery catalog, create multi-item request, view/cancel own pending requests |

No fourth production role is defined in the database seed.

## 3. Core workflow

```text
EMPLOYEE
Create request
  -> PENDING
ADMIN_MANAGER
Approve / Reject
  -> APPROVED or REJECTED
WAREHOUSE
Issue APPROVED request
  -> database transaction
  -> inventory decreases
  -> transaction history is saved
  -> request becomes ISSUED
```

Issuance is atomic: if any item has insufficient stock, the database transaction is rolled back and no item is deducted.

## 4. Main features

- Login/logout with secure password verification.
- Database-backed bearer session tokens with expiry.
- Server-side role authorization.
- Product CRUD with soft deactivation/restore.
- Supplier CRUD with soft deactivation/restore.
- Inventory search/filter/sort and low-stock detection.
- Warehouse stock in and direct stock out.
- Employee multi-item stationery request.
- Manager approval/rejection with rejection reason validation.
- Warehouse request issue with automatic inventory update.
- Transaction history.
- Reporting with type/date filters and transaction summary chart.
- User management for the manager role.
- Responsive business SaaS interface.
- API validation and structured error responses.
- PDO prepared statements and transaction integrity.
- Audit log for important actions.

## 5. Repository structure

```text
OfficeStock_Production_Ready/
├── backend/
│   ├── app/
│   │   ├── Core/
│   │   └── Services/
│   ├── database/
│   │   └── officestock_production.sql
│   ├── public/
│   │   ├── .htaccess
│   │   └── index.php
│   ├── .env.example
│   ├── Dockerfile
│   ├── docker-entrypoint.sh
│   └── render.yaml
├── frontend/
│   ├── src/
│   │   ├── index.html
│   │   ├── styles.css
│   │   └── js/
│   ├── scripts/build.mjs
│   ├── package.json
│   └── vercel.json
├── docs/
├── README.md
└── .gitignore
```

## 6. Test accounts

These accounts are seeded by `backend/database/officestock_production.sql` and are intended only for the assessment database.

| Role | Email | Password |
|---|---|---|
| Admin / Manager | `manager@officestock.demo` | `Manager@2026` |
| Warehouse | `warehouse@officestock.demo` | `Warehouse@2026` |
| Employee | `employee@officestock.demo` | `Employee@2026` |

Passwords are stored as password hashes in the database.

## 7. Environment variables

### Backend

Copy `backend/.env.example` only for developer testing. In production, configure variables in the hosting dashboard.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-PHP-API
CORS_ALLOWED_ORIGINS=https://YOUR-VERCEL-FRONTEND
SESSION_TTL_HOURS=8

DB_HOST=YOUR-MYSQL-HOST
DB_PORT=3306
DB_NAME=YOUR-DATABASE
DB_USER=YOUR-DATABASE-USER
DB_PASSWORD=YOUR-DATABASE-PASSWORD
```

### Frontend

Set this Vercel environment variable before deployment:

```env
API_BASE_URL=https://YOUR-PHP-API
```

The zero-dependency build script generates `dist/config.js` from the environment variable. No API URL is hard-coded into source code.

## 8. Database deployment

1. Create an online MySQL/MariaDB database.
2. Select the target database/schema in the provider.
3. Import `backend/database/officestock_production.sql`.
4. Confirm that the three test users, sample products and sample inventory are present.
5. Configure the database host, port, name, user and password in the PHP backend environment variables.
6. Never commit production database credentials.

The SQL file intentionally does not require permission to create a new database. It creates tables and seed data inside the database selected by the provider.

## 9. PHP backend deployment

The backend is prepared as a reproducible Docker web service.

1. Create a new web service from the repository and use `backend/` as the service root.
2. Build from `backend/Dockerfile`.
3. Configure the backend environment variables.
4. Configure `CORS_ALLOWED_ORIGINS` with the final Vercel frontend origin only.
5. Deploy.
6. Open:

```text
https://YOUR-PHP-API/health
```

Expected successful response:

```json
{
  "ok": true,
  "data": {
    "service": "OfficeStock PHP API",
    "status": "ok",
    "database": "connected"
  }
}
```

Do not mark deployment complete until this endpoint reports a connected production database.

## 10. Vercel frontend deployment

The frontend has no third-party runtime dependencies and has a reproducible build.

Local build verification:

```bash
cd frontend
API_BASE_URL=https://YOUR-PHP-API npm run build
```

For Vercel:

1. Import the Git repository.
2. Set Root Directory to `frontend`.
3. Set environment variable `API_BASE_URL` to the public PHP API URL.
4. Build command: `npm run build`.
5. Output directory: `dist`.
6. Deploy.
7. Open the public Vercel URL and test all three accounts.

The browser should follow this path:

```text
Vercel frontend -> HTTPS PHP API -> online MySQL/MariaDB
```

## 11. API overview

| Method | Endpoint | Roles |
|---|---|---|
| POST | `/api/auth/login` | Public |
| GET | `/api/auth/me` | Authenticated |
| POST | `/api/auth/logout` | Authenticated |
| GET | `/api/dashboard` | Authenticated |
| GET | `/api/products` | Authenticated |
| POST/PUT/DELETE | `/api/products...` | ADMIN_MANAGER |
| GET | `/api/suppliers` | Authenticated |
| POST/PUT/DELETE | `/api/suppliers...` | ADMIN_MANAGER |
| GET | `/api/inventory` | Authenticated |
| POST | `/api/inventory/stock-in` | WAREHOUSE |
| POST | `/api/inventory/stock-out` | WAREHOUSE |
| GET/POST | `/api/requests` | Role-scoped |
| POST | `/api/requests/{id}/review` | ADMIN_MANAGER |
| POST | `/api/requests/{id}/issue` | WAREHOUSE |
| GET | `/api/transactions` | ADMIN_MANAGER, WAREHOUSE |
| GET | `/api/reports` | ADMIN_MANAGER |
| GET/POST | `/api/users` | ADMIN_MANAGER |
| PATCH | `/api/users/{id}/status` | ADMIN_MANAGER |

## 12. Security controls

- `password_hash()` and `password_verify()`.
- Random 256-bit application session tokens.
- Only SHA-256 token hashes are stored in the database.
- Session expiry and server-side revocation on logout.
- `Authorization: Bearer` avoids cross-domain ambient cookies.
- Server-side role checks on every privileged endpoint.
- PDO prepared statements with emulated prepares disabled.
- Input validation and allow-lists for status/sort values.
- Database transactions and `SELECT ... FOR UPDATE` for inventory-changing workflows.
- CORS allow-list configured from environment variables.
- Structured JSON errors without production exception details.
- Security headers.
- Production secrets are excluded from source control.

Because authentication uses a bearer token in an explicit authorization header rather than an ambient authentication cookie, state-changing API calls are not vulnerable to conventional cookie-based CSRF. The token is stored in browser `sessionStorage`, so it is removed when the browser tab/session ends.

## 13. Production demo workflow

Use the public frontend URL:

1. Sign in as Employee.
2. Create a request for 10 Blue Ballpoint Pens and 5 A5 Notebooks.
3. Sign out.
4. Sign in as Admin / Manager.
5. Open Requests and approve the new PENDING request.
6. Sign out.
7. Sign in as Warehouse.
8. Open Approved Requests and issue it.
9. Open Inventory and verify the two stock values decreased.
10. Open Transactions and verify a `REQUEST_ISSUE` transaction.
11. Sign in as Admin / Manager and verify dashboard/report values changed.

## 14. Testing rule

The repository contains `docs/PRODUCTION_TEST_PLAN.md`. Production results must be recorded only after executing each test against the public frontend/backend/database.

- Use `PASS` only after the expected result is observed.
- Use `FAIL` when the actual result differs.
- Use `NOT RUN` before production execution.
- Do not invent production results.

## 15. Progressive Git history

Do not upload the entire project once at the end. Recommended meaningful commit sequence:

```text
Initial production project structure
Refactor PHP backend into JSON API
Add database-backed authentication sessions
Implement product and supplier CRUD
Implement inventory stock in and stock out
Implement request approval issue workflow
Add Vercel frontend and API integration
Add search filter sort and reporting
Add production database schema and test accounts
Configure backend Docker deployment
Configure Vercel build
Fix validation and authorization issues
Add production test plan
Update README and diagrams
Update final report with production evidence
```

## 16. Evidence still required before final submission

The source package is production-ready, but the following evidence cannot be truthfully generated without the team's real hosting accounts and public deployment:

- Actual GitHub repository URL.
- Actual Project Board URL.
- Actual Vercel URL.
- Actual PHP API URL.
- Actual online database provider/name.
- Public `/health` result showing database connectivity.
- Production screenshots.
- Executed production test results.
- Real progressive Git commit history.

Replace all `[TO BE UPDATED]` fields in the report only after these items exist.

## 17. AI/tool-use declaration

Generative AI was used to assist with architecture refactoring, code drafting, validation review, documentation and report preparation. The team remains responsible for verifying correctness, security, licensing and the ability to explain/modify the submitted system during the demonstration or viva.

## 18. Known limitations

- Single organization and single logical warehouse.
- No payment/accounting/CRM functions.
- No demand forecasting.
- No multi-company tenancy.
- No email/SMS notification service.
- No MFA/SSO.
- Production observability, backups and high availability depend on the selected hosting/database provider.

Stability and demonstrability are prioritized over extra features.
