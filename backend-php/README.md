# MyStore — PHP / MySQL backend

Drop-in replacement for the .NET API that runs on standard cPanel shared hosting (PHP 8 + MySQL). Same endpoints, same JSON shapes, so the existing vanilla-JS frontend works without any changes beyond `js/config.js`.

- **Stack:** PHP 8.1+ (PDO MySQL), no Composer, no external libraries.
- **Auth:** HS256 JWT (custom 60-line implementation) + `password_hash()` BCrypt.
- **Routing:** Tiny custom router; everything goes through `index.php`.
- **Stub payment service:** returns `pi_stub_*` IDs identical to the .NET version.

---

## Local development

Requirements:
- PHP 8.1+ with `pdo_mysql`, `mbstring`, `openssl` extensions.
- A MySQL or MariaDB instance.

```bash
# 1. Create a DB and a user (adjust to your local setup):
mysql -u root -p <<'SQL'
CREATE DATABASE mystore_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mystore'@'localhost' IDENTIFIED BY 'mystore_pw';
GRANT ALL ON mystore_php.* TO 'mystore'@'localhost';
SQL

# 2. Import the schema + seed data:
mysql -u mystore -pmystore_pw mystore_php < sql/schema.sql

# 3. Configure:
cp config.example.php config.php
# Edit config.php — set db.user, db.pass, db.name, jwt.secret.

# 4. Run the dev server:
php -S 127.0.0.1:8081 index.php
```

Then change `frontend/js/config.js` to `apiBase: "http://127.0.0.1:8081"` and open the frontend with Live Server (`http://127.0.0.1:5500`).

Default admin: `admin@mystore.local` / `Admin#123`.

---

## Deploy to cPanel (mystore2.com example)

The whole site goes onto one domain, no subdomain needed. Frontend at `https://mystore2.com/`, API at `https://mystore2.com/api/...`.

### 1. Create the MySQL database

In cPanel → **MySQL® Databases**:

1. Create database, e.g. `mystore2_db` (cPanel will prefix it with your username).
2. Create user, e.g. `mystore2_dbuser`, generate a strong password — **save it**.
3. Add the user to the database with **ALL PRIVILEGES**.

### 2. Import the schema

cPanel → **phpMyAdmin** → select your new database → **Import** tab → upload `backend-php/sql/schema.sql` → **Go**.

You should now see 6 tables (`users`, `categories`, `products`, `cart_items`, `orders`, `order_items`) and 14 seeded products.

### 3. Upload the backend

cPanel → **File Manager** → navigate to `public_html/`.

Create a folder called `api` (so the public URL becomes `mystore2.com/api/...`). Upload **the contents** of `backend-php/` into `public_html/api/`:

```
public_html/api/
├── .htaccess
├── index.php
├── config.php             ← you'll create this in step 4
├── config.example.php
└── src/
    ├── Database.php
    ├── Helpers.php
    ├── JWT.php
    ├── Router.php
    └── Controllers/...
```

Skip `sql/` — it isn't needed at runtime.

### 4. Create `config.php`

In File Manager: copy `config.example.php` to `config.php` and edit it (right-click → **Edit**):

```php
'db' => [
    'host'    => 'localhost',
    'name'    => 'mystore2_db',
    'user'    => 'mystore2_dbuser',
    'pass'    => 'YOUR_REAL_DB_PASSWORD',
    ...
],
'jwt' => [
    // Generate a strong secret. Run this on your machine and paste the output:
    //   php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
    'secret' => 'paste-the-64-char-hex-string-here',
    ...
],
'cors_origins' => [
    'https://mystore2.com',
    'https://www.mystore2.com',
    // Strictly speaking, when frontend + API are on the same domain you don't
    // need any CORS entry — keep these for safety / future cross-origin testing.
],
```

### 5. Upload the frontend

Upload everything from `frontend/` directly into `public_html/` (so `index.html` is at `public_html/index.html`).

Edit `public_html/js/config.js` and make sure it reads:

```js
window.MYSTORE_CONFIG = { apiBase: "" };
```

(Empty `apiBase` makes the frontend call `/api/...` on the same domain — no CORS needed.)

### 6. Verify

- `https://mystore2.com/api/health` → `{"status":"ok"}`
- `https://mystore2.com/api/categories` → array of 4 categories
- `https://mystore2.com/` → home page with Apple products loading from the API

If `/api/health` 404s, your hosting likely doesn't have `mod_rewrite` enabled. Open a support ticket asking them to enable `AllowOverride All` for your account, or move to a host that does.

If you see "config.php missing", it means File Manager didn't upload it — check the folder.

---

## API surface

All endpoints return JSON. The same shape as the previous .NET API:

| Method | Path | Auth | Body | Returns |
|---|---|---|---|---|
| `POST` | `/api/auth/register` | — | `{ fullName, email, password }` | `{ token, user }` |
| `POST` | `/api/auth/login` | — | `{ email, password }` | `{ token, user }` |
| `GET`  | `/api/users/me` | Bearer | — | `{ id, fullName, email, role }` |
| `GET`  | `/api/categories` | — | — | `[{ id, name, slug, description, productCount }]` |
| `GET`  | `/api/products` | — | query: `search, category, minPrice, maxPrice, sort, featured, page, pageSize` | `{ items, total, page, pageSize }` |
| `GET`  | `/api/products/featured?take=8` | — | — | `[Product]` |
| `GET`  | `/api/products/{id}` | — | — | `Product` |
| `POST` `PUT` `DELETE` | `/api/products(/id)` | Admin | — | CRUD |
| `GET`  | `/api/cart` | Bearer | — | `{ items, subtotal, totalQuantity }` |
| `POST` | `/api/cart/items` | Bearer | `{ productId, quantity }` | cart |
| `PUT`  | `/api/cart/items/{productId}` | Bearer | `{ quantity }` | cart |
| `DELETE` | `/api/cart/items/{productId}` | Bearer | — | cart |
| `DELETE` | `/api/cart` | Bearer | — | cart |
| `POST` | `/api/orders/checkout` | Bearer | `{ shippingAddress, city, postalCode, country, items? }` | `{ order, clientSecret, publishableKey }` |
| `GET`  | `/api/orders` | Bearer | — | `[Order]` |
| `GET`  | `/api/orders/{id}` | Bearer | — | `Order` |
| `GET`  | `/api/orders/all` | Admin | — | `[Order]` |
| `PUT`  | `/api/orders/{id}/status` | Admin | `{ status: 0..4 }` | `Order` |
| `GET`  | `/api/health` | — | — | `{ status: "ok" }` |

`Order.status` is an integer matching the .NET enum: `0=Pending, 1=Paid, 2=Shipped, 3=Delivered, 4=Cancelled`.

---

## Troubleshooting

| Problem | Likely cause | Fix |
|---|---|---|
| `Internal Server Error` on every request | `.htaccess` rewrite blocked | Hosting must have `mod_rewrite` and `AllowOverride All` |
| `Unauthorised` on every cart call | `Authorization` header stripped by Apache | The `.htaccess` already re-injects it; if still broken, ask host to allow `HTTP_AUTHORIZATION` |
| `config.php missing` | File didn't upload, or wrong filename | Copy `config.example.php` → `config.php` in File Manager |
| `Database connection failed` | Wrong DB credentials, or DB user not added to DB | Double-check **MySQL® Databases** in cPanel |
| Frontend shows products but cart says "Unauthorised" | `apiBase` mismatch — login token issued by one origin, sent to another | Set `apiBase: ""` in `config.js` |
