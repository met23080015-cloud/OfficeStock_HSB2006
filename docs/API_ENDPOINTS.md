# PHP API Endpoint Reference

All protected endpoints use:

```http
Authorization: Bearer <session-token>
Content-Type: application/json
```

## Authentication

- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`

## Dashboard / metadata

- `GET /api/dashboard`
- `GET /api/meta`

## Products

- `GET /api/products`
- `POST /api/products` - ADMIN_MANAGER
- `PUT /api/products/{id}` - ADMIN_MANAGER
- `DELETE /api/products/{id}` - ADMIN_MANAGER
- `POST /api/products/{id}/restore` - ADMIN_MANAGER

## Suppliers

- `GET /api/suppliers`
- `POST /api/suppliers` - ADMIN_MANAGER
- `PUT /api/suppliers/{id}` - ADMIN_MANAGER
- `DELETE /api/suppliers/{id}` - ADMIN_MANAGER
- `POST /api/suppliers/{id}/restore` - ADMIN_MANAGER

## Inventory

- `GET /api/inventory`
- `POST /api/inventory/stock-in` - WAREHOUSE
- `POST /api/inventory/stock-out` - WAREHOUSE
- `GET /api/transactions` - ADMIN_MANAGER / WAREHOUSE
- `GET /api/reports` - ADMIN_MANAGER

## Stationery requests

- `GET /api/requests` - role scoped
- `POST /api/requests` - EMPLOYEE
- `POST /api/requests/{id}/cancel` - EMPLOYEE
- `POST /api/requests/{id}/review` - ADMIN_MANAGER
- `POST /api/requests/{id}/issue` - WAREHOUSE

## Users

- `GET /api/users` - ADMIN_MANAGER
- `POST /api/users` - ADMIN_MANAGER
- `PATCH /api/users/{id}/status` - ADMIN_MANAGER
