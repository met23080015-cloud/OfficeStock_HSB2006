# Final Architecture

OfficeStock uses a separated production architecture so the public frontend can be served independently from the PHP business backend.

```text
Lecturer / User Browser
          |
          v
Vercel Frontend
- Responsive business UI
- Role-aware navigation
- No business data embedded
          |
          | HTTPS + JSON + Bearer session token
          v
PHP 8.x API
- Authentication / authorization
- Product / supplier CRUD
- Inventory
- Request -> Approval -> Issue
- Validation / reporting
          |
          | PDO prepared statements
          v
Online MySQL/MariaDB
- Master data
- Inventory
- Request headers/details
- Transaction headers/details
- Auth sessions
- Audit log
```

The frontend build receives its public API base URL from `API_BASE_URL`. The backend receives database credentials and the allowed frontend origin only from hosting environment variables.
