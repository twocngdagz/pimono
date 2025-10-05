# Mini Wallet (High-Concurrency Demo)

A simplified digital wallet API + SPA (Laravel 12 + Vue 3) focused on MySQL for high‑concurrency balance updates, idempotent transfers, and real‑time broadcasting.

## 1. Features
- POST `/api/transactions` – create transfer
- GET `/api/transactions` – recent history (incoming + outgoing) + current balance
- Atomic debit/credit with `SELECT ... FOR UPDATE`
- Commission (1.5%) rounding half‑up to nearest cent (sender‑paid)
- Optional `idempotency_key` for safe retries
- Real‑time updates to both sender & receiver over `private-user.{id}` channels

## 2. Tech Stack
- Backend: Laravel 12 (PHP 8.3)
- Frontend: Vue 3 (Composition API) + Vite
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
- Idempotency: identical (sender, receiver, amount) + same key returns original; differing parameters → 409 conflict.

### 5.1 Domain Error Schema (Wallet Exceptions)
```json
{
  "error": "Insufficient balance to perform transfer.",
  "type": "InsufficientFunds",
  "code": "wallet.insufficient_funds"
}
```
Fields:
- error: Human-readable message (English, not yet localized)
- type: Exception class basename (stable for this test project)
- code: Machine-readable snake_case code namespaced with `wallet.` prefix

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
| (Any future WalletException w/out override) | 422 Unprocessable Entity | wallet.<dynamic> | Default domain rule failure |

Implementation details:
- Each exception extends `WalletException` and may override `httpStatus()`.
- Global renderer (see `bootstrap/app.php`) calls `httpStatus()` + `errorCode()` to produce unified envelope.
- Machine codes are generated via snake_case of class name prefixed with `wallet.` (e.g., `wallet.insufficient_funds`).
- Idempotent replay with identical parameters returns the first transaction (status 201 retained for demo); conflict only when parameters differ.

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

### Reset & Reseed
```bash
php artisan migrate:fresh --seed
```
Override on-the-fly (one-off):
```bash
DEMO_SEED=false DEMO_SEED_MAX=true php artisan db:seed --class=DatabaseSeeder
```

## 10. API Examples
Create transfer:
```bash
curl -X POST http://localhost:8000/api/transactions \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <token>' \
  -d 'receiver_id=2' -d 'amount=25.00' -d 'idempotency_key=6f8b1c0e-...'
```
List history:
```bash
curl -H 'Authorization: Bearer <token>' http://localhost:8000/api/transactions
```

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
| Rate limiting | Throttle POST transfer (e.g., `throttle:60,1`) |
| Idempotency race | Gracefully handle rare uuid unique constraint collision |
| Commission sink | System account / ledger entries for commission accumulation |
| History query | UNION optimization + covering indexes for huge tables |

## 15. Cleanup
```bash
docker compose down -v
```

---
