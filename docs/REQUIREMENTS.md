# OfficeStock Requirements Baseline

## Business problem

Small and medium organizations may manage office supplies using disconnected spreadsheets, chat messages or paper records. This can make current stock, request status, approval responsibility and issue history difficult to trace.

## Objective

Provide one database-driven web workflow for product/supplier master data, stock visibility, internal stationery requests, approval, warehouse issue and traceable inventory transactions.

## Functional requirements

- FR01: Authenticate users and log out/revoke active sessions.
- FR02: Implement exactly three roles: ADMIN_MANAGER, WAREHOUSE, EMPLOYEE.
- FR03: Return role-specific dashboards using database data.
- FR04: Product CRUD with search and sort.
- FR05: Supplier CRUD with search/filter.
- FR06: Inventory current quantity, low-stock detection, search/filter/sort.
- FR07: Warehouse multi-item stock in.
- FR08: Warehouse direct stock out with negative-stock prevention.
- FR09: Employee creates a multi-item stationery request.
- FR10: Employee tracks/cancels own PENDING request.
- FR11: Admin/Manager approves or rejects PENDING requests.
- FR12: Warehouse issues only APPROVED requests.
- FR13: Issuance atomically reduces stock, saves a transaction and changes request to ISSUED.
- FR14: Transaction history is available to manager and warehouse roles.
- FR15: Manager reporting filters by transaction type/date and returns database-derived summary.
- FR16: Manager can create and lock/unlock assessment users.

## Non-functional requirements

- NFR01: Responsive UI for desktop/tablet/mobile.
- NFR02: PHP 8.x backend and PDO database connection.
- NFR03: MySQL/MariaDB relational database with PK/FK/constraints.
- NFR04: Prepared statements for user-controlled query values.
- NFR05: Password hashing and secure application-session strategy.
- NFR06: Role authorization on backend endpoints.
- NFR07: Production credentials supplied using environment variables.
- NFR08: Public frontend deployment and public backend API deployment.
- NFR09: Business data must not be simulated with static frontend JSON.
- NFR10: Production tests and evidence must be reproducible and documented.
