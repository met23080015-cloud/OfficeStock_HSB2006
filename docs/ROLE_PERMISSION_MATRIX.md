# Role & Permission Matrix

| Function | ADMIN_MANAGER | WAREHOUSE | EMPLOYEE |
|---|:---:|:---:|:---:|
| Login / Logout | Yes | Yes | Yes |
| Dashboard | Yes | Yes | Yes |
| View active product catalog | Yes | Yes | Yes |
| Product create/update/deactivate/restore | Yes | No | No |
| Supplier CRUD | Yes | No | No |
| View inventory | Yes | Yes | No |
| Low-stock visibility | Yes | Yes | No |
| Stock In | No | Yes | No |
| Direct Stock Out | No | Yes | No |
| Create stationery request | No | No | Yes |
| View own requests | No | No | Yes |
| Cancel own PENDING request | No | No | Yes |
| View all requests | Yes | Limited to approved/issued | No |
| Approve / Reject | Yes | No | No |
| Issue approved request | No | Yes | No |
| Transaction history | Yes | Yes | No |
| Reporting | Yes | No | No |
| User management | Yes | No | No |

Authorization is enforced in the PHP backend. Hidden frontend navigation alone is not considered a security control.
