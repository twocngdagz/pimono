# Mini Wallet (High-Concurrency Demo)

A simplified digital wallet API + SPA (Laravel 12 + Vue 3) focused on MySQL for high‑concurrency balance updates, idempotent transfers, and real‑time broadcasting.
For convenience a site has been deployed at: https://pimono-pmggx7hz.on-forge.com/
## 1. Features
- POST `/api/transactions` – create transfer (idempotent with automatic client key)
- GET `/api/transactions` – recent history (incoming + outgoing) + current balance
- Atomic debit/credit with `SELECT ... FOR UPDATE`
- Commission (1.5%) rounding half‑up to nearest cent (sender‑paid)
- Automatic idempotency key generation on frontend (UUID v4) sent via `Idempotency-Key` header
- Idempotent replay returns cached result (200 OK + `Idempotent-Replay: true` header)
- Real‑time updates to both sender & receiver over `private-user.{id}` channels
- Per-request correlation via `X-Request-ID` header (auto-generated if absent)
- Basic rate limiting on transfer endpoint (`throttle:60,1`)

## 2. Tech Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Node v20 and Vue 3 (Composition API) + Vite
- Database: MySQL 8.x (SQLite used only internally for automated tests)
- Auth: Laravel Sanctum
- Real-time: Pusher Channels
- Queue / Sessions / Cache: Database (Redis optional for future optimization)

## 3. Quick Start (Docker + MySQL)
```bash
# 1. Clone
git clone <repo-url> mini-wallet && cd mini-wallet

# 2. Copy environment file (includes MySQL defaults)
cp .env.example .env
# Defaults (already set):
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=wallet
# DB_USERNAME=root
# DB_PASSWORD=

# 3. Start infrastructure (MySQL + Redis containers provision DB & user automatically)
docker compose up -d mysql redis

# 4. Install PHP dependencies
composer install

# 5. Generate app key
php artisan key:generate

# 6. Run migrations & (optional) seed demo data
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder  # optional if DEMO_SEED=true

# 7. Install JS deps & build (or use dev server)
npm install
npm run build   # or: npm run dev

# 8. Serve API + frontend
php artisan serve
```
App available at: http://localhost:8000

### 3.1 Running WITHOUT Docker (Local MySQL)
If you prefer your own local MySQL server, you have two options:

Option A (quick – reuse existing root user, blank password):
1. Ensure your local MySQL server is running.
2. Create the database only (no user creation needed):
```sql
CREATE DATABASE IF NOT EXISTS wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
3. Update `.env` (override the defaults) to match your root credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wallet
DB_USERNAME=root
DB_PASSWORD=
```
4. Continue with install, migrate, (optional) seed, and serve steps.

Option B (isolated demo user – as provided in .env.example):
```sql
CREATE DATABASE wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wallet'@'%' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON wallet.* TO 'wallet'@'%';
FLUSH PRIVILEGES;
```
Leave `.env` defaults as-is for this option.

Then (either option):
```bash
composer install
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
npm install && npm run dev &
php artisan serve
```

Note on queues: A queue worker is NOT required for broadcasting right now because `TransferCompleted` implements `ShouldBroadcastNow`, which sends the event synchronously during the HTTP request. Start a queue worker only if you later (a) switch the event to `ShouldBroadcast` for async dispatch, or (b) introduce queued jobs (emails, cleanup, heavy tasks):
```bash
php artisan queue:work
```

## 4. Environment Variables (Key)
| Variable | Default in .env.example | Purpose |
|----------|-------------------------|---------|
| DB_CONNECTION | mysql | Must remain mysql (tests override to sqlite) |
| DB_HOST | 127.0.0.1 | MySQL host (container or local) |
| DB_PORT | 3306 | MySQL port |
| DB_DATABASE | wallet | Schema name (create manually if using root) |
| DB_USERNAME | wallet | Use `root` (with blank password) if you prefer – adjust `.env` |
| DB_PASSWORD | secret | Blank if using root with no password |
| PUSHER_APP_KEY / SECRET / APP_ID | (placeholders) | Real-time broadcasting credentials |
| BROADCAST_CONNECTION | pusher | Set to log in tests automatically |
| DEMO_* | (see table) | Control demo seeding volumes |

## 5. Transfer Semantics
- Commission = 1.5% of amount (sender only). Example: send 100.00 → sender debited 101.50, receiver credited 100.00.
- Integer cents math; half-up rounding for commission.
- Balances stored (not recalculated from history) for O(1) reads.
- Idempotency:
  - First successful request with a new key → 201 Created.
  - Exact replay (same key + same sender/receiver/amount) → 200 OK with body `idempotent_replay: true` and response header `Idempotent-Replay: true`.
  - Same key + different parameters → 409 Conflict (IdempotencyConflict).

### 5.1 Domain Error Schema (Wallet Exceptions)
```json
{
  "error": "Insufficient balance to perform transfer.",
  "type": "InsufficientFunds",
  "code": "wallet.insufficient_funds",
  "request_id": "a7e6f1c2-..."
}
```
Added field:
- request_id: Correlation ID for this HTTP attempt (matches `X-Request-ID` header)

Validation errors (input field validation) still use Laravel's default shape:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "amount": ["Amount must be at least 0.01."]
  }
}
```

### 5.2 Exception → HTTP Status Mapping
| Exception | HTTP Status | code value | Rationale |
|-----------|-------------|-----------|-----------|
| InvalidAmountFormat | 400 Bad Request | wallet.invalid_amount_format | Syntactic/format issue with amount field |
| AmountMustBeGreaterThanZero | 400 Bad Request | wallet.amount_must_be_greater_than_zero | Numeric value provided but violates minimum semantic constraint |
| CannotTransferToSelf | 422 Unprocessable Entity | wallet.cannot_transfer_to_self | Payload structurally valid; business rule violation |
| InsufficientFunds | 422 Unprocessable Entity | wallet.insufficient_funds | Request understood; cannot apply due to current balance state |
| ReceiverNotFound | 422 Unprocessable Entity | wallet.receiver_not_found | Referenced receiver_id not usable (treated as semantic failure, not URL resource) |
| SenderNotFound | 422 Unprocessable Entity | wallet.sender_not_found | (Extremely rare) sender row missing mid-process |
| IdempotencyConflict | 409 Conflict | wallet.idempotency_conflict | Same idempotency key reused with different parameters |
| AmountTooLarge | 400 Bad Request | wallet.amount_too_large | User has sufficient balance but amount + commission exceeds internal integer cents range |
| (Any future WalletException w/out override) | 422 Unprocessable Entity | wallet.<dynamic> | Default domain rule failure |

Implementation details:
- Each exception extends `WalletException` and may override `httpStatus()`.
- Global renderer (see `bootstrap/app.php`) calls `httpStatus()` + `errorCode()` to produce unified envelope.
- Machine codes are generated via snake_case of class name prefixed with `wallet.` (e.g., `wallet.insufficient_funds`).
- Idempotent replay returns HTTP 200 OK + `Idempotent-Replay: true`; original creation returns 201.

### 5.3 Idempotency & Request IDs
| Aspect | Mechanism |
|--------|-----------|
| Client key generation | Frontend generates UUID v4 per submission |
| Transport | `Idempotency-Key` header (body `idempotency_key` accepted for backward compatibility) |
| Replay detection | Same key + identical sender/receiver/amount before or after race recovery |
| Replay response | 200 OK, `idempotent_replay: true`, header `Idempotent-Replay: true` |
| Conflict | 409 Conflict if parameters differ for same key |
| Race handling | Unique constraint caught; transaction re-fetched; conflict or replay resolved deterministically |
| Correlation | `X-Request-ID` header accepted; generated if absent; echoed back & embedded in error JSON |
| Rate limit | `throttle:60,1` protects POST spam |

Replay example (same key used twice with identical body):
1st response: 201 Created `{ idempotent_replay: false }`
2nd response: 200 OK `{ idempotent_replay: true }` + header `Idempotent-Replay: true`

Headers involved:
- `Idempotency-Key`: Client-supplied logical operation key.
- `Idempotent-Replay`: Present ("true") only on a replay response.
- `X-Request-ID`: Correlation ID per HTTP attempt (client may supply; server generates if omitted).

### 5.4 Recommended Client Behavior
- Always generate a fresh UUID for each logical transfer attempt (already implemented in provided Vue component).
- If HTTP timeout occurs, safely retry with the same `Idempotency-Key`.
- Treat 200 + `Idempotent-Replay: true` as success (no duplicate debit).

## 6. Concurrency & Integrity
| Concern | Approach |
|---------|----------|
| Race conditions | Row locking (FOR UPDATE) on sender & receiver inside one transaction |
| Deadlocks | Deterministic ordering (ascending user IDs) |
| Double submit | Optional idempotency key reuse returns original transaction |
| Rollback safety | Debit, credit, and transaction insert are atomic |
| Scalability | Stored balance avoids scanning large transaction table |

Future enhancement: double-entry ledger for full audit and reconciliation.

## 7. Real-Time Flow
1. Transfer persists balances + transaction.
2. `TransferCompleted` event broadcasts immediately to sender & receiver channels.
3. Frontend appends transaction and updates balances live.

## 8. Running Tests
Tests use in-memory SQLite (no MySQL needed):
```bash
composer test
```
Full verification (style + static analysis + tests):
```bash
composer verify
```

## 9. Demo Seeding
Adjust volumes via `.env` (e.g., `DEMO_USERS`, `DEMO_TRANSFERS`).
Seed:
```bash
php artisan db:seed --class=DatabaseSeeder
```

### 9.1 Seeding Environment Variables Explained
| Variable | Type | Default | Applies To | Description |
|----------|------|---------|------------|-------------|
| DEMO_SEED | bool | true | DemoDataSeeder | When true (and not production or tests) seeds the standard demo dataset (moderate size). |
| DEMO_USERS | int | 20 | DemoDataSeeder | Number of additional random demo users (excluding base Alice/Bob/Charlie + Whale). |
| DEMO_TRANSFERS | int | 200 | DemoDataSeeder | Approximate count of random successful transfers to generate transaction depth. |
| DEMO_FAILED | int | 5 | DemoDataSeeder | Number of synthetic FAILED transactions inserted (status='failed'). |
| DEMO_BIG_TRANSFERS | csv decimal list | 1000.00,25000.00,99999.99 | DemoDataSeeder | Large transfer amounts executed from Whale user to random users. |
| DEMO_SEED_MAX | bool | false | LargeDemoDataSeeder | When true (and not production or tests) runs the large volume seeder AFTER the standard one (unless DEMO_SEED=false). |
| DEMO_USERS_MAX | int | 250 | LargeDemoDataSeeder | Additional high-volume users to create (labelled Load User N). |
| DEMO_TRANSFERS_MAX | int | 7500 | LargeDemoDataSeeder | Additional random successful transfers (larger range). |
| DEMO_FAILED_MAX | int | 150 | LargeDemoDataSeeder | Additional FAILED transactions for large dataset. |
| DEMO_BIG_TRANSFERS_MAX | csv decimal list | 50000.00,125000.00,250000.00,500000.00 | LargeDemoDataSeeder | Very large transfer amounts from Mega Whale to random users. |

#### Behavior Notes
- Order: `DemoDataSeeder` runs first if `DEMO_SEED=true`, then `LargeDemoDataSeeder` runs if `DEMO_SEED_MAX=true`.
- Combined Datasets: If both are enabled you get the sum (standard + large). To get only the large dataset, set `DEMO_SEED=false` and `DEMO_SEED_MAX=true`.
- Production Safety: Both seeders are skipped automatically in production and during unit tests regardless of flags.
- Failed Transactions: These are manually inserted to demonstrate a `failed` status. They do not affect balances because balance mutations happen only through successful transfers inside the service layer.
- Whale / Mega Whale: High-balance users used to guarantee successful large transfers independent of random user balances.

#### Example Configurations
Minimal seed (only base 3 users + Whale, no extras):
```bash
DEMO_SEED=false
DEMO_SEED_MAX=false
```
Standard mid-size demo (defaults – you can omit since these are in .env.example):
```bash
DEMO_SEED=true
DEMO_USERS=20
DEMO_TRANSFERS=200
DEMO_FAILED=5
DEMO_SEED_MAX=false
```
Large-only using max defaults (skip normal, use large dataset defaults):
```bash
DEMO_SEED=false
DEMO_SEED_MAX=true
DEMO_USERS_MAX=250
DEMO_TRANSFERS_MAX=7500
```
Both (aggregate default + large defaults):
```bash
DEMO_SEED=true
DEMO_SEED_MAX=true
```
Custom larger large-only example (override defaults):
```bash
DEMO_SEED=false
DEMO_SEED_MAX=true
DEMO_USERS_MAX=300
DEMO_TRANSFERS_MAX=10000
```

### 9.2 Demo Accounts & Credentials
When you seed (`php artisan db:seed --class=DatabaseSeeder`) the following baseline users are ALWAYS created (independent of DEMO_SEED flags):

| Role / Purpose | Name | Email | Password | Starting Balance |
|----------------|------|-------|----------|------------------|
| Primary demo user | Alice Example | alice@example.com | password1 | 1000.00 |
| Secondary demo user | Bob Example | bob@example.com | password2 | 500.00 |
| Low-balance demo user | Charlie Example | charlie@example.com | password3 | 50.00 |

Additional users created ONLY when `DEMO_SEED=true` (default in non‑production):

| Role | Name | Email | Password | Balance |
|------|------|-------|----------|---------|
| High-balance source for large transfers | Whale User | whale@example.com | password | 1,000,000.00 |
| Random demo users (count = DEMO_USERS, default 20) | Demo User N | demoN@example.com | password | Random 10.00 – 25,000.00 |

If `DEMO_SEED_MAX=true` (large dataset seeder) runs, extra high‑volume accounts are added:

| Role | Name | Email | Password | Balance |
|------|------|-------|----------|---------|
| Very high-balance source for very large transfers | Mega Whale | mega.whale@example.com | password | 5,000,000.00 |
| Load testing users (count = DEMO_USERS_MAX, default 250) | Load User N | loadN@example.com | password | Random 5.00 – 75,000.00 |

Notes:
- Random users (`Demo User N`, `Load User N`) all share the simple password `password` for convenience.
- Passwords are intentionally weak—DO NOT deploy with these defaults.
- Whale / Mega Whale users enable generation of very large successful transfers used in example history & performance tests.
- Failed transactions inserted by seeders use random sender/receiver pairs and do not modify balances.

Quick Login Reference (after default seed):
```text
Alice (sender)   alice@example.com   password1
Bob (receiver)   bob@example.com     password2
Charlie (low)    charlie@example.com password3
Whale (if DEMO_SEED=true)        whale@example.com       password
Mega Whale (if DEMO_SEED_MAX=true) mega.whale@example.com password
```

### 9.3 Demo & Load User Credential Patterns (Explicit)
In addition to the named baseline accounts above, seeders generate indexed demo and load testing users that all share the same simple password for convenience in local development.

| User Type | Email Pattern | Password | Example First Two |
|-----------|---------------|----------|-------------------|
| Demo User (when `DEMO_SEED=true`) | `demoN@example.com` where N starts at 1 | `password` | `demo1@example.com`, `demo2@example.com` |
| Load User (when `DEMO_SEED_MAX=true`) | `loadN@example.com` where N starts at 1 | `password` | `load1@example.com`, `load2@example.com` |

Quick examples:
```text
demo1@example.com / password
demo7@example.com / password
load1@example.com / password
load125@example.com / password
```
Number of users created depends on `DEMO_USERS` (for Demo Users) and `DEMO_USERS_MAX` (for Load Users). If you change those environment variables the highest N will adjust accordingly.

Security note: These credentials are intentionally weak and MUST NOT be used in any production or publicly exposed environment.

## 10. API Examples

### 10.1 Authentication (Two Supported Flows)
You can authenticate either with a browser **session (Sanctum SPA flow)** or with a **personal access token (Bearer)**.

#### A) Session / Cookie (SPA) Flow (used by the Vue frontend)
1. Get CSRF cookie (establishes XSRF-TOKEN + session cookie):
```bash
curl -i -c cookies.txt -b cookies.txt \
  -H 'Accept: application/json' \
  http://localhost:8000/sanctum/csrf-cookie
```
2. Login with credentials:
```bash
curl -i -c cookies.txt -b cookies.txt \
  -H 'Accept: application/json' \
  -H 'X-XSRF-TOKEN: $(grep XSRF-TOKEN cookies.txt | tail -n1 | awk '{print $7}' | perl -MURI::Escape -ne 'print uri_unescape($_)')' \
  -X POST http://localhost:8000/login \
  -d 'email=alice@example.com' -d 'password=password1'
```
3. Call protected API (cookies carry auth automatically):
```bash
curl -s -c cookies.txt -b cookies.txt -H 'Accept: application/json' \
  http://localhost:8000/api/transactions | jq
```
4. Logout (destroys session):
```bash
curl -X POST -c cookies.txt -b cookies.txt -H 'X-XSRF-TOKEN: $(grep XSRF-TOKEN cookies.txt | tail -n1 | awk '{print $7}' | perl -MURI::Escape -ne 'print uri_unescape($_)')' \
  http://localhost:8000/logout -i
```

Pros: Automatic rotation / revocation with session invalidation; mitigates token leakage risk (httpOnly cookie).  
Cons: More steps in raw curl (CSRF handling).

#### B) Personal Access Token (Bearer) Flow
1. Request a token using email/password:
```bash
curl -s -X POST http://localhost:8000/api/token \
  -H 'Accept: application/json' \
  -d 'email=alice@example.com' -d 'password=password1' -d 'device_name=cli' | jq
```
Response:
```json
{
  "token_type": "Bearer",
  "token": "1|2v3...<redacted>...pQ",
  "user": { "id": 1, "email": "alice@example.com", "name": "Alice Example", "balance": "1000.00" }
}
```
2. Use the token:
```bash
TOKEN="<paste-token-here>"
curl -s http://localhost:8000/api/transactions \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' | jq
```
3. Create a transfer with token & idempotency key:
```bash
IDEMP=$(uuidgen || echo 00000000-0000-4000-8000-000000000000)
curl -s -X POST http://localhost:8000/api/transactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $IDEMP" \
  -H 'Accept: application/json' \
  -d 'receiver_id=2' -d 'amount=25.00' | jq
```
4. Replay (same key) returns HTTP 200 with `idempotent_replay=true`:
```bash
curl -s -X POST http://localhost:8000/api/transactions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $IDEMP" \
  -H 'Accept: application/json' \
  -d 'receiver_id=2' -d 'amount=25.00' | jq
```

Revocation: Delete/unset the token (no endpoint provided for per-token revocation here, but Sanctum supports deleting tokens server-side if needed). For this demo you can just discard it or rotate by requesting a new one.

Security Notes:
- Treat personal access tokens like passwords (store securely, prefer ephemeral lifetimes in production).
- Use HTTPS in any non-local environment.
- Do NOT check real tokens into version control or logs.

### 10.2 Transfer Endpoint Examples
Create transfer (new):
```bash
curl -X POST http://localhost:8000/api/transactions \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>' \
  -H 'Idempotency-Key: 1c1d7c3d-9b3c-4c2e-bf90-9a50b9a2c2dd' \
  -d 'receiver_id=2' -d 'amount=25.00'
```
Response (first time):
```json
{
  "data": {
    "id": 42,
    "uuid": "1c1d7c3d-9b3c-4c2e-bf90-9a50b9a2c2dd",
    "sender_id": 1,
    "receiver_id": 2,
    "amount": "25.00",
    "commission_fee": "0.38",
    "status": "success",
    "created_at": "2025-10-06T12:34:56.000000Z",
    "idempotent_replay": false
  }
}
```
Replay (identical request with same Idempotency-Key):
```json
{
  "data": {
    "id": 42,
    "uuid": "1c1d7c3d-9b3c-4c2e-bf90-9a50b9a2c2dd",
    "sender_id": 1,
    "receiver_id": 2,
    "amount": "25.00",
    "commission_fee": "0.38",
    "status": "success",
    "created_at": "2025-10-06T12:34:56.000000Z",
    "idempotent_replay": true
  }
}
```
Replay response headers (subset):
```
HTTP/1.1 200 OK
Idempotent-Replay: true
X-Request-ID: 5f1c9c1a-...
```
Conflict (different amount with same key):
```json
{
  "error": "Idempotency key reused with different parameters.",
  "type": "IdempotencyConflict",
  "code": "wallet.idempotency_conflict",
  "request_id": "c6d6c8e4-..."
}
```
Status: 409 Conflict.

## 11. Frontend Dev Mode
```bash
php artisan serve
npm run dev
```
Ensure Pusher keys in `.env` if you want live broadcasting.

## 12. Performance Notes
- Integer cents logic avoids floating precision issues.
- Minimal write set: two balance updates + one insert.
- History query limited to recent 50 for quick UI hydration (pagination can be added later).

## 13. Security Considerations
- Sanctum guards API routes.
- Private channel auth restricts real-time access to owner.
- Self-transfer blocked at validation & domain layer.
- Idempotency prevents accidental duplicate charging.

## 14. Known Next Steps (Optional Improvements)
| Area | Enhancement |
|------|-------------|
| Pagination | Add cursor or since_id pagination for history |
| Rate limiting | Already applied (adjust as usage grows) |
| Idempotency race | Already handled (unique constraint recovery) |
| Commission sink | System account / ledger entries for commission accumulation |
| History query | UNION optimization + covering indexes for huge tables |
| Observability | Add structured logs & metrics (wallet.transfer.count, wallet.idempotency.replay) |
| Validation | Enforce max amount & field length constraints |
| Amount limits | Consider migrating to big integer math (Brick\Math) to remove current representable upper bound (≈ 92,233,720,368,547,758.07 units) |

## 15. Cleanup
```bash
docker compose down -v
```

## Assignment Requirements Coverage
| Requirement | Status | Notes |
|-------------|--------|-------|
| Users table with balance | Implemented | DECIMAL(20,2) balance updated atomically inside DB transaction |
| Transactions table (sender, receiver, amount, commission, status, timestamps) | Implemented | Includes composite indexes for pagination |
| POST /api/transactions (transfer) | Implemented | Idempotent (client key), commission applied, 201 vs 200 replay semantics |
| GET /api/transactions (history + balance) | Implemented | Cursor pagination via `before_id`, merged directions with UNION |
| Commission 1.5% debited from sender | Implemented | Integer cents, half‑up rounding |
| Atomic money transfer | Implemented | Single DB transaction with row locks |
| High concurrency safety | Implemented | Ordered locking (sorted IDs) + idempotency race recovery |
| Real-time broadcast on success | Implemented | Pusher + ShouldBroadcastNow event `TransferCompleted` |
| Real-time UI update (balances + history) | Implemented | Echo listener updates list & balance instantly |
| Validation (amount, receiver exists, self-transfer, funds) | Implemented | FormRequest + domain exception mapping |
| Data integrity (rollback on failure) | Implemented | Exceptions abort transaction; no partial state |
| Performance strategy (scaled data set) | Implemented | Stored balance, composite indexes, UNION, minimal writes |
| Optional/Extra: Notifications (DB store) | Omitted by design | Frontend shows real-time toast directly from event (no DB dependency) |

## Real-Time Setup (Pusher)
To enable real-time broadcasting locally or in deployment:
1. Set / confirm in `.env`:
```
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-cluster
```
2. Expose the key to Vite (already wired in `.env.example`):
```
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```
3. Rebuild frontend (Vite) if it was already running.
4. Open two browser sessions (different users); perform a transfer; the receiver’s dashboard updates instantly.
5. For tests / offline dev without Pusher credentials set:
```
BROADCAST_CONNECTION=log
```
(phpunit.xml already forces `log` to avoid external calls during test runs.)

6. When deploying to forge or production server, ensure the environment variable is set.
```
SESSION_DOMAIN=example.com or null for local development
```

7. If you have different url for local like ".test" for herd or other than localhost, you need to set it in .env. You also need to set this on your production server.
```aiignore
SANCTUM_STATEFUL_DOMAINS=example.com or pimono.test
```

### Environment Variable Quick Reference (Real-Time)
| Variable | Required (Prod) | Purpose |
|----------|-----------------|---------|
| BROADCAST_CONNECTION | Yes | Must be `pusher` for live events (or `log` for local no-op) |
| PUSHER_APP_ID | Yes | Pusher app identifier |
| PUSHER_APP_KEY | Yes | Public key used by frontend |
| PUSHER_APP_SECRET | Yes | Server-side auth for event broadcast |
| PUSHER_APP_CLUSTER | Yes | Cluster region (e.g., `eu`) |

## Pagination & Indexing Strategy
`GET /api/transactions` merges outgoing and incoming transactions using two indexed scans:
- Composite indexes: `(sender_id, id)` and `(receiver_id, id)` support efficient range scans.
- Queries are UNION ALL’d, then ordered by `id DESC` and limited.
- Cursor parameter: `before_id` (fetches older rows where `id < before_id`).
- Limit parameter: `limit` (default 20 in UI, capped at 50 server-side).
- Response meta:
```
"meta": {
  "limit": 20,
  "next_before_id": 9123,
  "has_more": true
}
```
This avoids large OR conditions and scales for millions of records by letting the database perform two narrow index traversals.

## Performance & Architecture Summary
| Concern | Approach |
|---------|----------|
| Balance reads | Stored `users.balance` (O(1)), updated atomically |
| Double-spend / race | Row locking (`SELECT ... FOR UPDATE`) with deterministic ordering (sort user IDs) |
| Idempotency retries | Client-generated key + lookup + replay or 409 conflict |
| Race on create (duplicate key) | Unique constraint caught → re-query existing transaction (replay) |
| Commission precision | Integer cent math + half-up rounding (15/1000) |
| Query load (history) | UNION of two indexed scans + cursor pagination |
| Write path | Two user updates + one insert = minimal IO |
| Large amounts | 20,2 DECIMAL + integer intermediary prevents float drift |
| Real-time latency | `ShouldBroadcastNow` (synchronous) for immediate UI response |
| Error consistency | Central WalletException mapping -> JSON (code + request_id) |
| Observability | `X-Request-ID` correlation + idempotent replay headers |

## Testing Summary
| Area | Examples |
|------|----------|
| Service logic | Commission rounding (0.01, 0.67, large values), idempotency, exceptions |
| API | Validation errors, insufficient funds, idempotency conflict & replay (201 vs 200) |
| Exception mapping | Status codes & JSON structure (400 / 422 / 409) |
| Concurrency | Alternating stress test (ensures no deadlocks + sum checks) |
| Pagination (manual) | Cursor logic exercised via UI (optional to extend with feature tests) |
| Balance integrity | Assertions after each transfer path |

## Additional Notes (Operational)
- **Replay semantics:** first success = 201 Created; identical replay = 200 OK + `Idempotent-Replay: true` header and `idempotent_replay: true` body flag.
- **Commission example:** 1.00 → commission 0.02 (1.5% of 1.00 = 0.015 half-up => 0.02).
- **Cursor pagination:** stable because IDs are monotonic increasing primary keys.
- **Scaling path:** Introduce ledger entries (double-entry) if full audit reconstruction or multi-currency is added later.
- **Optional improvements (future):** Add metrics (transfer count, replay count), server-side cached unread notifications (if DB notification layer desired later), direction filters (`?direction=in`).
