-- MyStore — MySQL schema + seed data.
-- Import this file once via cPanel phpMyAdmin (or `mysql < schema.sql`)
-- to create all tables and seed the 14 Apple products + admin user.
--
-- Default admin login: admin@mystore.local / Admin#123

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------
-- Tables
-- ---------------------------------------------------------------

CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(256) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'Customer',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    description VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150)   NOT NULL,
    description  VARCHAR(2000)  NOT NULL DEFAULT '',
    price        DECIMAL(18,2)  NOT NULL,
    stock        INT            NOT NULL DEFAULT 0,
    image_url    VARCHAR(500)   NOT NULL DEFAULT '',
    brand        VARCHAR(80)    NULL,
    is_featured  TINYINT(1)     NOT NULL DEFAULT 0,
    category_id  INT UNSIGNED   NOT NULL,
    created_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_product_category (category_id),
    INDEX idx_product_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    quantity    INT          NOT NULL,
    PRIMARY KEY (user_id, product_id),
    CONSTRAINT fk_cart_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED  NOT NULL,
    total             DECIMAL(18,2) NOT NULL,
    -- 0=Pending, 1=Paid, 2=Shipped, 3=Delivered, 4=Cancelled
    status            TINYINT       NOT NULL DEFAULT 0,
    shipping_address  VARCHAR(300)  NOT NULL,
    city              VARCHAR(60)   NOT NULL,
    postal_code       VARCHAR(20)   NOT NULL,
    country           VARCHAR(60)   NOT NULL,
    payment_intent_id VARCHAR(100)  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_order_user (user_id),
    INDEX idx_order_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED  NOT NULL,
    product_id  INT UNSIGNED  NOT NULL,
    quantity    INT           NOT NULL,
    unit_price  DECIMAL(18,2) NOT NULL,
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_oi_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Seed: admin user (password = Admin#123, BCrypt hashed)
-- ---------------------------------------------------------------
-- Hash generated with PHP password_hash('Admin#123', PASSWORD_BCRYPT)
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Admin', 'admin@mystore.local', '$2y$10$MWEaZqLlB7iuJWpZdJnQTOkXe8A9yNmReHIUo/VHBt5co4PWkHUBy', 'Admin');

-- ---------------------------------------------------------------
-- Seed: categories
-- ---------------------------------------------------------------
INSERT INTO categories (name, slug, description) VALUES
('iPhone',      'iphone',      'Latest iPhones — Pro, Pro Max, and standard models.'),
('MacBook',     'macbook',     'MacBook Air and MacBook Pro powered by Apple silicon.'),
('iPad',        'ipad',        'iPad, iPad Air, iPad mini, and iPad Pro.'),
('Accessories', 'accessories', 'AirPods, Apple Watch, chargers, and more.');

-- ---------------------------------------------------------------
-- Seed: products (14 real Apple products)
-- ---------------------------------------------------------------
SET @c_iphone = (SELECT id FROM categories WHERE slug='iphone');
SET @c_mac    = (SELECT id FROM categories WHERE slug='macbook');
SET @c_ipad   = (SELECT id FROM categories WHERE slug='ipad');
SET @c_accs   = (SELECT id FROM categories WHERE slug='accessories');

INSERT INTO products (name, description, price, stock, image_url, brand, is_featured, category_id) VALUES
('iPhone 15 Pro Max 256GB',
 '6.7" Super Retina XDR display, A17 Pro chip, titanium design, 5x telephoto camera.',
 1199, 25,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-7inch-naturaltitanium?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1693342290295',
 'Apple', 1, @c_iphone),

('iPhone 15 Pro 128GB',
 '6.1" Super Retina XDR, A17 Pro, titanium frame, USB-C, Action Button.',
 999, 30,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-pro-finish-select-202309-6-1inch-bluetitanium?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1692923776595',
 'Apple', 1, @c_iphone),

('iPhone 15 128GB',
 '6.1" display with Dynamic Island, A16 Bionic, 48MP main camera, USB-C.',
 799, 60,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-15-finish-select-202309-6-1inch-pink?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1692924188181',
 'Apple', 1, @c_iphone),

('iPhone 14 128GB',
 '6.1" Super Retina XDR display, A15 Bionic, dual-camera system, Crash Detection.',
 699, 40,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/iphone-14-finish-select-202209-6-1inch-blue?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1660753093656',
 'Apple', 0, @c_iphone),

('MacBook Pro 14" M3 Pro',
 '14.2" Liquid Retina XDR display, M3 Pro chip, 18GB RAM, 512GB SSD.',
 1999, 15,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/mbp14-spacegray-select-202310?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1697311054290',
 'Apple', 1, @c_mac),

('MacBook Air 13" M2',
 '13.6" Liquid Retina display, Apple M2 chip, 8GB unified memory, 256GB SSD.',
 1099, 35,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/mba13-midnight-select-202402?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1707414914194',
 'Apple', 1, @c_mac),

('MacBook Air 15" M3',
 '15.3" Liquid Retina display, M3 chip, 8GB unified memory, 256GB SSD.',
 1299, 22,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/mba15-starlight-select-202402?wid=904&hei=840&fmt=jpeg&qlt=90&.v=1707414985464',
 'Apple', 0, @c_mac),

('iPad Pro 11" M4',
 'Ultra Retina XDR display, M4 chip, Apple Pencil Pro support, 256GB.',
 999, 18,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-pro-13-select-wifi-spaceblack-202405?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1713308271133',
 'Apple', 1, @c_ipad),

('iPad Air 11" M2',
 '11" Liquid Retina display, M2 chip, USB-C, 128GB.',
 599, 28,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-air-11-select-wifi-blue-202405?wid=2560&hei=1440&fmt=p-jpg&qlt=80&.v=1713308179770',
 'Apple', 0, @c_ipad),

('iPad mini',
 '8.3" Liquid Retina display, A15 Bionic, Touch ID, 64GB.',
 499, 30,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/ipad-mini-select-wifi-purple-202109?wid=940&hei=1112&fmt=png-alpha&.v=1631661775000',
 'Apple', 0, @c_ipad),

('AirPods Pro (2nd gen)',
 'Active Noise Cancellation, Adaptive Audio, USB-C charging case.',
 249, 80,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/MTJV3?wid=572&hei=572&fmt=jpeg&qlt=95&.v=1694014871985',
 'Apple', 1, @c_accs),

('Apple Watch Series 9 45mm',
 'Always-On Retina display, S9 SiP, Double Tap gesture, GPS.',
 429, 45,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/watch-card-40-s9-202309?wid=680&hei=528&fmt=p-jpg&qlt=95&.v=1693945562692',
 'Apple', 1, @c_accs),

('Magic Keyboard for iPad Pro',
 'Built-in trackpad, USB-C pass-through charging, floating cantilever design.',
 299, 25,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/MJQJ3?wid=572&hei=572&fmt=jpeg&qlt=95&.v=1617126613000',
 'Apple', 0, @c_accs),

('Apple Pencil Pro',
 'Squeeze gestures, Find My, barrel roll, haptic feedback.',
 129, 60,
 'https://store.storeimages.cdn-apple.com/4982/as-images.apple.com/is/MX2D3?wid=572&hei=572&fmt=jpeg&qlt=95&.v=1713380790067',
 'Apple', 0, @c_accs);
