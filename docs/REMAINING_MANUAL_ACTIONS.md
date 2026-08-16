# Remaining Manual Actions Required From the Team

The code package has been refactored and statically verified, but public deployment cannot be truthfully marked complete until the team connects real hosting accounts.

- [ ] Create/finalize the GitHub repository and Project Board.
- [ ] Commit the project progressively using meaningful commits.
- [ ] Provision an online MySQL/MariaDB database.
- [ ] Import `backend/database/officestock_production.sql`.
- [ ] Deploy the PHP backend from `backend/Dockerfile`.
- [ ] Set backend environment variables.
- [ ] Verify public `/health` returns database `connected`.
- [ ] Deploy `frontend/` on Vercel.
- [ ] Set Vercel `API_BASE_URL`.
- [ ] Set backend `CORS_ALLOWED_ORIGINS` to the final Vercel origin.
- [ ] Run TC01-TC20 against public URLs.
- [ ] Record real PASS/FAIL/Actual Result values.
- [ ] Capture production screenshots.
- [ ] Replace report URL/evidence placeholders.
- [ ] Ensure every member can explain their assigned area.
- [ ] Rename submission files according to the lecturer format.

Do not mark any blocked item complete without evidence.
