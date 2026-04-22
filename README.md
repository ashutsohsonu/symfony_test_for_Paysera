# Fund Transfer API

A production-ready REST API for transferring funds between accounts, built with **PHP 8.3 + Symfony 7.2**, following **CQRS** and **SOLID** principles.


## Key Design Decisions

| Concern | Decision | Why |
|---|---|---|
| **Money representation** | Integer minor units (cents) | Eliminates IEEE 754 floating-point errors |
| **Concurrency** | Pessimistic write locks + deterministic lock ordering | Prevents race conditions and deadlocks under high load |
| **Idempotency** | Redis key+TTL (24h) before transaction | Exactly-once transfer execution for retries/duplicate requests |
| **CQRS** | Separate Command/Query buses via Symfony Messenger | Independent scaling, clear intent separation |
| **Entities** | Domain aggregates + separate ORM entities | Anti-corruption layer: domain stays pure, no Doctrine leaking in |
| **Rate limiting** | Atomic Redis Lua script | No race condition between check and increment |
| **Error responses** | RFC 7807 Problem Details JSON | Consistent, machine-readable error format |
| **State machine** | `PENDING → COMPLETED / FAILED` | Explicit transitions, invalid states impossible |

---

## Prerequisites

Choose one of the two setup paths below:

| Path | Requirements |
|------|-------------|
| **Option A — Docker** *(recommended)* | Docker Desktop (Windows) or Docker Engine (Linux) |
| **Option B — Without Docker** | PHP 8.3, Composer, MySQL 8.0, Redis 7, a web server |

---

## Option A — Setup with Docker *(Recommended)*

Docker bundles PHP-FPM, Nginx, MySQL, and Redis in isolated containers — no manual installation needed.

### Install Docker

<details>
<summary><strong>🪟 Windows — Install Docker Desktop</strong></summary>

1. Download **Docker Desktop for Windows** from [https://www.docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop)
2. Run the installer — it will also install WSL 2 (Windows Subsystem for Linux) automatically
3. Restart your PC when prompted
4. Open Docker Desktop and wait until the taskbar icon shows **"Docker Desktop is running"**
5. Open **PowerShell** or **Windows Terminal** and verify:

```powershell
docker --version
docker-compose --version
```

> **Note:** Docker Desktop requires Windows 10 64-bit (Build 19041 or later) or Windows 11.

</details>

<details>
<summary><strong>🐧 Linux — Install Docker Engine</strong></summary>

```bash
# Ubuntu / Debian
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg

sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Add your user to docker group (avoids using sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker --version
docker compose version
```

</details>

### Run with Docker

```bash
# 1. Clone the repository
git clone <your-repo-url>
cd fund-transfer-api

# 2. Copy environment file
# Windows PowerShell:
copy .env .env.local

# Linux / macOS:
cp .env .env.local

# 3. Start all services (PHP-FPM, Nginx, MySQL, Redis)
docker-compose up -d --build

# 4. Wait for all containers to be healthy (~30 seconds)
docker-compose ps

# 5. Install PHP dependencies
docker-compose exec app composer install

# 6. Run database migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# 7. (Optional) Load seed data
docker-compose exec app php bin/console doctrine:fixtures:load --no-interaction

# API is now available at http://localhost:8080
```

---

## Option B — Setup Without Docker

Use this path if Docker is not available. You will manually install and configure PHP, MySQL, and Redis.

### 🪟 Windows (Without Docker)

#### 1. Install PHP 8.3

1. Download the **PHP 8.3 Non-Thread Safe ZIP** from [https://windows.php.net/download](https://windows.php.net/download)
2. Extract to `C:\php`
3. Add `C:\php` to your system **PATH** environment variable
4. Copy `php.ini-development` → `php.ini` inside `C:\php`
5. In `php.ini`, uncomment these extensions:
   ```ini
   extension=curl
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=zip
   ```
6. Verify: `php --version`

#### 2. Install Composer

Download and run the installer from [https://getcomposer.org/Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe)

Verify: `composer --version`

#### 3. Install MySQL 8.0

1. Download **MySQL Installer** from [https://dev.mysql.com/downloads/installer/](https://dev.mysql.com/downloads/installer/)
2. During setup, choose **"Server Only"** or **"Custom"**
3. Set root password to `rootsecret` (or update `.env.local` later)
4. After install, create the database:

```powershell
mysql -u root -p
```
```sql
CREATE DATABASE fund_transfer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON fund_transfer.* TO 'app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 4. Install Redis on Windows

Option 1 — **WSL2** (recommended):
```powershell
wsl --install        # if WSL not installed
# Inside WSL terminal:
sudo apt install redis-server
sudo service redis-server start
```

Option 2 — **Memurai** (native Windows Redis-compatible server):  
Download from [https://www.memurai.com/](https://www.memurai.com/) and install — it runs as a Windows service.

#### 5. Configure & Run the App

```powershell
cd "d:\ashutosh\php test\f453d4537f88eb9e457315180f3ed2ee"

# Update .env.local for local MySQL/Redis
copy .env .env.local
```

Edit `.env.local`:
```env
DATABASE_URL="mysql://app:secret@127.0.0.1:3306/fund_transfer?serverVersion=8.0&charset=utf8mb4"
REDIS_URL=redis://127.0.0.1:6379
```

```powershell
# Install dependencies
composer install

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Load seed fixtures (optional)
php bin/console doctrine:fixtures:load --no-interaction

# Start the Symfony dev server (built-in PHP server)
php -S 127.0.0.1:8080 -t public/

# API is now available at http://127.0.0.1:8080
```

---

### 🐧 Linux (Without Docker)

#### 1. Install PHP 8.3

```bash
# Ubuntu 22.04 / 24.04
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y \
    php8.3 php8.3-fpm php8.3-cli php8.3-mysql \
    php8.3-redis php8.3-intl php8.3-zip \
    php8.3-mbstring php8.3-xml php8.3-curl

php --version
```

#### 2. Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

#### 3. Install MySQL 8.0

```bash
sudo apt-get install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql

# Secure and create database
sudo mysql -u root <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'rootsecret';
CREATE DATABASE fund_transfer CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON fund_transfer.* TO 'app'@'localhost';
FLUSH PRIVILEGES;
EOF
```

#### 4. Install Redis

```bash
sudo apt-get install -y redis-server
sudo systemctl start redis-server
sudo systemctl enable redis-server
redis-cli ping   # should return PONG
```

#### 5. Configure & Run the App

```bash
cd /path/to/fund-transfer-api

cp .env .env.local
```

Edit `.env.local`:
```env
DATABASE_URL="mysql://app:secret@127.0.0.1:3306/fund_transfer?serverVersion=8.0&charset=utf8mb4"
REDIS_URL=redis://127.0.0.1:6379
```

```bash
# Install dependencies
composer install

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Load seed fixtures (optional)
php bin/console doctrine:fixtures:load --no-interaction

# Start the Symfony dev server
php -S 127.0.0.1:8080 -t public/

# API is now available at http://127.0.0.1:8080
```

#### Run Tests (Without Docker)

```bash
# Create test database
mysql -u app -psecret -e "CREATE DATABASE IF NOT EXISTS fund_transfer_test CHARACTER SET utf8mb4;"

# Update .env.test.local
echo 'DATABASE_URL="mysql://app:secret@127.0.0.1:3306/fund_transfer_test?serverVersion=8.0"' > .env.test.local

# Run migrations for test env
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Load test fixtures
php bin/console doctrine:fixtures:load --env=test --no-interaction

# Run tests
php bin/phpunit
```

---

## Troubleshooting (Without Docker)

| Problem | Fix |
|---------|-----|
| `php: command not found` | PHP not in PATH — re-check installation step |
| `Connection refused` (MySQL) | Run `sudo systemctl start mysql` (Linux) or start MySQL service (Windows) |
| `Connection refused` (Redis) | Run `sudo systemctl start redis` or check Memurai is running |
| `redis extension not found` | Install `php8.3-redis` (Linux) or enable `extension=redis` in php.ini (Windows) |
| `APP_SECRET` warning | Set a real 32-char random string in `.env.local` |

---

## API Reference

### Create Account

```http
POST /api/accounts
Content-Type: application/json

{
    "owner_name": "Alice Smith",
    "currency": "USD",
    "initial_balance": 100000
}
```

`initial_balance` is in **minor units** (100000 = $1,000.00).

**Response 201:**
```json
{
    "account_id": "019660e2-1234-7abc-8def-000000000001",
    "message": "Account created successfully."
}
```

---

### Get Account

```http
GET /api/accounts/{id}
```

**Response 200:**
```json
{
    "data": {
        "id": "019660e2-...",
        "owner_name": "Alice Smith",
        "currency": "USD",
        "balance": 1000.00,
        "balance_minor_units": 100000,
        "active": true,
        "created_at": "2026-04-20T12:00:00+00:00",
        "updated_at": "2026-04-20T12:00:00+00:00"
    }
}
```

---

### Transfer Funds

```http
POST /api/transfers
Content-Type: application/json

{
    "source_account_id":      "019660e2-...",
    "destination_account_id": "019660e2-...",
    "amount":                 5000,
    "currency":               "USD",
    "idempotency_key":        "unique-client-generated-key-abc123"
}
```

**`amount`** is in minor units (5000 = $50.00).  
**`idempotency_key`**: Generate once per transfer intent. Repeating the same key returns the original transfer — safe for retries.

**Response 201:**
```json
{
    "transfer_id": "019660f1-...",
    "message": "Transfer completed successfully."
}
```

**Error Response 422 (RFC 7807):**
```json
{
    "type": "/errors/domain",
    "title": "Unprocessable Content",
    "status": 422,
    "detail": "Insufficient funds: cannot subtract 10000 from 5000 USD"
}
```

---

### Get Transfer

```http
GET /api/transfers/{id}
```

**Response 200:**
```json
{
    "data": {
        "id": "019660f1-...",
        "source_account_id": "...",
        "destination_account_id": "...",
        "currency": "USD",
        "amount": 50.00,
        "amount_minor_units": 5000,
        "status": "completed",
        "failure_reason": null,
        "idempotency_key": "unique-client-key",
        "created_at": "2026-04-20T12:00:00+00:00",
        "updated_at": "2026-04-20T12:00:00+00:00"
    }
}
```

---

## Supported Currencies

| Code | Name |
|------|------|
| USD | US Dollar |
| EUR | Euro |
| GBP | British Pound |
| INR | Indian Rupee |

---

## Running Tests

```bash
# 1. Ensure test DB is running
docker-compose up -d test_db

# 2. Create test schema
docker-compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction

# 3. Load test fixtures
docker-compose exec app php bin/console doctrine:fixtures:load --env=test --no-interaction

# 4. Run all tests
docker-compose exec app php bin/phpunit

# Run only unit tests (no DB needed)
docker-compose exec app php bin/phpunit --testsuite=Unit

# Run only integration tests
docker-compose exec app php bin/phpunit --testsuite=Integration

# With coverage report
docker-compose exec app php bin/phpunit --coverage-text
```

---

## Rate Limiting

The API enforces **100 requests per 60 seconds** per IP address using an atomic Redis Lua script.  
Exceeding the limit returns `429 Too Many Requests`.

---

## Concurrency & Deadlock Prevention

Under high load with concurrent transfers:

1. Both accounts are locked with `SELECT ... FOR UPDATE`
2. Accounts are **always locked in lexicographic UUID order** — regardless of which is source or destination
3. This ensures thread A and thread B always acquire locks in the same order, making deadlocks structurally impossible

---

## What I Would Add With More Time

- [ ] **Distributed locking** (Redis Redlock) for multi-node deployments where DB locks aren't sufficient
- [ ] **Event sourcing** — replay account history from domain events instead of current-state snapshots
- [ ] **Webhook notifications** on transfer completion/failure
- [ ] **Currency conversion** support via exchange rate service
- [ ] **Admin endpoints** to freeze/unfreeze accounts
- [ ] **OpenAPI/Swagger** spec generation
- [ ] **Prometheus metrics** endpoint for transfer throughput, error rates, p99 latency
- [ ] **Circuit breaker** pattern if calling external currency conversion APIs
- [ ] **Soft delete / audit log** table for compliance

---

