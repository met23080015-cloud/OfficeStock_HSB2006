# OfficeStock Production Test Plan

**Rule:** Execute these tests against the public frontend, public PHP API and online database. Status remains `NOT RUN` until production execution is complete.

| ID | Test | Preconditions | Steps / Input | Expected Result | Actual Result | Status |
|---|---|---|---|---|---|---|
| TC01 | Login Admin/Manager | Production online | Login with manager account | Manager dashboard opens; manager navigation is visible | [TO BE UPDATED] | NOT RUN |
| TC02 | Login Warehouse | Production online | Login with warehouse account | Warehouse dashboard opens | [TO BE UPDATED] | NOT RUN |
| TC03 | Login Employee | Production online | Login with employee account | Employee dashboard opens | [TO BE UPDATED] | NOT RUN |
| TC04 | Wrong password | Login page | Valid email + wrong password | Login rejected without revealing whether account exists | [TO BE UPDATED] | NOT RUN |
| TC05 | Role authorization | Employee logged in | Call manager-only endpoint / navigate to manager feature | Backend returns 403; privileged operation is not performed | [TO BE UPDATED] | NOT RUN |
| TC06 | Product CRUD | Manager logged in | Create, edit, deactivate, restore product | Data persists in production database | [TO BE UPDATED] | NOT RUN |
| TC07 | Supplier CRUD | Manager logged in | Create, edit, deactivate, restore supplier | Data persists correctly | [TO BE UPDATED] | NOT RUN |
| TC08 | Search/filter/sort | Authenticated | Search SKU/name; low-stock filter; sort quantity | Returned list matches filters/sort | [TO BE UPDATED] | NOT RUN |
| TC09 | Employee create request | Employee logged in | Create 10 pens + 5 notebooks | New request stored as PENDING with two request_items | [TO BE UPDATED] | NOT RUN |
| TC10 | Manager approve | PENDING request | Manager approves | Status changes PENDING -> APPROVED; reviewer recorded | [TO BE UPDATED] | NOT RUN |
| TC11 | Manager reject | Separate PENDING request | Reject with reason | Status REJECTED; review_note stored | [TO BE UPDATED] | NOT RUN |
| TC12 | Warehouse issue | APPROVED request | Issue request | Request becomes ISSUED; transaction created | [TO BE UPDATED] | NOT RUN |
| TC13 | Inventory update | TC12 completed | Compare stock before/after | Quantities decrease exactly by issued amounts | [TO BE UPDATED] | NOT RUN |
| TC14 | Over-issue rejection | Stock lower than requested | Attempt stock out / issue too much | Request rejected; database transaction rolls back; no partial deduction | [TO BE UPDATED] | NOT RUN |
| TC15 | Low-stock alert | Product at/below minimum | Open Inventory | Product marked LOW | [TO BE UPDATED] | NOT RUN |
| TC16 | Dashboard | Production data exists | Open dashboards | Metrics come from database and reflect current data | [TO BE UPDATED] | NOT RUN |
| TC17 | Reporting | Manager logged in | Filter report by type/date | Matching production transaction rows and summary shown | [TO BE UPDATED] | NOT RUN |
| TC18 | Logout | User logged in | Logout then reuse old token | Session token is revoked; protected endpoint returns 401 | [TO BE UPDATED] | NOT RUN |
| TC19 | Validation | Authenticated | Quantity 0/negative, invalid email, duplicate request item | Backend returns validation error; invalid data not persisted | [TO BE UPDATED] | NOT RUN |
| TC20 | Security | Production online | Inspect secret handling, CORS, bearer auth, SQL inputs | No production secret in repo; unauthorized origin/role blocked; prepared statements used | [TO BE UPDATED] | NOT RUN |

## Defect log

| Defect ID | Related Test | Description | Severity | Owner | Status |
|---|---|---|---|---|---|
| DEF-001 | [TO BE UPDATED] | [TO BE UPDATED] | [TO BE UPDATED] | [TO BE UPDATED] | OPEN/CLOSED |

Do not create fake defects or fake PASS results. Fill this table from real production testing.
