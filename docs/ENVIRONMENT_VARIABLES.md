# Environment Variables

## PHP backend

| Variable | Required | Example purpose |
|---|---|---|
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | Keep `false` in production |
| `APP_URL` | Yes | Public PHP API URL |
| `CORS_ALLOWED_ORIGINS` | Yes | Exact Vercel origin(s), comma separated |
| `SESSION_TTL_HOURS` | Yes | Application session lifetime |
| `DB_HOST` | Yes | Online database host |
| `DB_PORT` | Yes | Database port |
| `DB_NAME` | Yes | Database/schema name |
| `DB_USER` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |

## Vercel frontend

| Variable | Required | Purpose |
|---|---|---|
| `API_BASE_URL` | Yes | Public PHP API origin |

Do not put production values into committed files.
