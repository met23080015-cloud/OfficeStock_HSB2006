# Recommended Progressive Commit Sequence

Use meaningful commits as work is reviewed. Do not create fake commits with false authorship.

1. `chore: initialize OfficeStock production repository`
2. `docs: add requirements and role permission matrix`
3. `refactor: convert PHP application into JSON API architecture`
4. `feat: add database-backed authentication sessions`
5. `feat: implement product and supplier CRUD API`
6. `feat: implement inventory search stock in and stock out`
7. `feat: implement employee stationery request workflow`
8. `feat: implement manager approval and rejection`
9. `feat: implement warehouse issue and atomic inventory update`
10. `feat: add Vercel-ready responsive frontend`
11. `feat: integrate frontend with PHP production API`
12. `feat: add transaction reporting and low-stock indicators`
13. `security: add CORS allow-list validation and secret configuration`
14. `deploy: add backend Docker and Vercel build configuration`
15. `test: add production test plan and verification evidence`
16. `fix: resolve production deployment defects`
17. `docs: update README diagrams and final report evidence`

A commit should be made after a team member reviews the related change, not merely to inflate commit count.
