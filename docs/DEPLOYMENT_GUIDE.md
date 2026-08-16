# OfficeStock Deployment Guide

## Target architecture

```text
Public browser
  -> Vercel frontend
  -> HTTPS PHP JSON API
  -> Online MySQL/MariaDB
```

## Phase 1 - Repository

1. Create the final GitHub repository now.
2. Add the source in meaningful phases rather than one bulk upload.
3. Protect `.env` and production credentials with `.gitignore`.
4. Add a Project Board with Backlog / In Progress / Review / Done.

## Phase 2 - Online database

1. Provision a MySQL/MariaDB database that accepts connections from the selected backend host.
2. Import `backend/database/officestock_production.sql` into the selected database.
3. Confirm seed users, products and inventory.
4. Record only non-secret provider information for the report.
5. Put credentials only in backend hosting environment variables.

## Phase 3 - PHP backend

1. Create a Docker web service with root directory `backend`.
2. Deploy `backend/Dockerfile`.
3. Configure:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL`
   - `CORS_ALLOWED_ORIGINS`
   - `SESSION_TTL_HOURS=8`
   - database environment variables
4. Deploy.
5. Open `/health`.
6. Do not proceed to final evidence until `"database":"connected"` is returned.

## Phase 4 - Vercel frontend

1. Create a Vercel project from the GitHub repository.
2. Root directory: `frontend`.
3. Environment variable:
   - `API_BASE_URL=https://YOUR-PHP-API`
4. Build command: `npm run build`
5. Output directory: `dist`
6. Deploy.
7. Add the final Vercel origin to backend `CORS_ALLOWED_ORIGINS`.
8. Redeploy backend if CORS value changed.

## Phase 5 - Production verification

Test in a normal browser using only public URLs:

```text
frontend -> PHP API -> production database
```

Run all 20 test cases in `PRODUCTION_TEST_PLAN.md`.

## Phase 6 - Evidence capture

Capture production screenshots for:

1. Public Vercel URL.
2. Login page.
3. Employee dashboard.
4. Employee request form and submitted PENDING request.
5. Manager approval.
6. Warehouse approved-request queue.
7. Warehouse issue success.
8. Inventory after issue.
9. Transaction history.
10. Low-stock state.
11. Manager report.
12. Responsive/mobile layout.
13. PHP `/health` response.
14. Vercel deployment screen.
15. GitHub commit history / Project Board.

## Phase 7 - Final report update

Only after production testing:

- Replace `[TO BE UPDATED]` URLs.
- Insert actual screenshots.
- Change `NOT RUN` to real `PASS` / `FAIL`.
- Add unresolved defects.
- Confirm diagrams match final endpoints/tables/roles.
