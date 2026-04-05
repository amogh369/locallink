-- ============================================================
-- LocalLink - PostgreSQL Schema
-- Paste this ENTIRE file into Supabase SQL Editor and click RUN
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    user_id       SERIAL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    phone         VARCHAR(15),
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(20) NOT NULL DEFAULT 'customer'
                      CHECK (role IN ('customer','shop_owner')),
    latitude      NUMERIC(10,8),
    longitude     NUMERIC(11,8),
    address       VARCHAR(255),
    avatar        VARCHAR(255),
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    updated_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS shops (
    shop_id      SERIAL PRIMARY KEY,
    owner_id     INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    category     VARCHAR(30) NOT NULL DEFAULT 'grocery'
                     CHECK (category IN ('grocery','restaurant','pharmacy',
                            'electronics','clothing','plumbing','electrical',
                            'cleaning','other')),
    latitude     NUMERIC(10,8) NOT NULL,
    longitude    NUMERIC(11,8) NOT NULL,
    address      VARCHAR(255),
    phone        VARCHAR(15),
    opening_time TIME DEFAULT '08:00:00',
    closing_time TIME DEFAULT '22:00:00',
    is_open      SMALLINT DEFAULT 1,
    rating       NUMERIC(3,2) DEFAULT 0.00,
    image        VARCHAR(255),
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS products (
    product_id   SERIAL PRIMARY KEY,
    shop_id      INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    name         VARCHAR(150) NOT NULL,
    description  TEXT,
    price        NUMERIC(10,2) NOT NULL,
    unit         VARCHAR(30) DEFAULT 'piece',
    stock        INT DEFAULT 100,
    image        VARCHAR(255),
    is_available SMALLINT DEFAULT 1,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS services (
    service_id    SERIAL PRIMARY KEY,
    shop_id       INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    name          VARCHAR(150) NOT NULL,
    description   TEXT,
    price         NUMERIC(10,2) NOT NULL,
    duration_mins INT DEFAULT 60,
    is_available  SMALLINT DEFAULT 1,
    created_at    TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS orders (
    order_id         SERIAL PRIMARY KEY,
    user_id          INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    shop_id          INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    total_amount     NUMERIC(10,2) NOT NULL DEFAULT 0,
    status           VARCHAR(25) NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending','confirmed','preparing',
                                'out_for_delivery','delivered','cancelled')),
    delivery_address VARCHAR(255),
    delivery_lat     NUMERIC(10,8),
    delivery_lng     NUMERIC(11,8),
    notes            TEXT,
    payment_method   VARCHAR(10) DEFAULT 'cash',
    payment_status   VARCHAR(10) DEFAULT 'pending',
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS order_items (
    item_id    SERIAL PRIMARY KEY,
    order_id   INT NOT NULL REFERENCES orders(order_id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(product_id) ON DELETE CASCADE,
    quantity   INT NOT NULL DEFAULT 1,
    price      NUMERIC(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS bookings (
    booking_id   SERIAL PRIMARY KEY,
    user_id      INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    service_id   INT NOT NULL REFERENCES services(service_id) ON DELETE CASCADE,
    shop_id      INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    booking_date DATE NOT NULL,
    time_slot    VARCHAR(20) NOT NULL,
    status       VARCHAR(15) NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending','confirmed','completed','cancelled')),
    notes        TEXT,
    total_price  NUMERIC(10,2),
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notifications (
    notif_id   SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    title      VARCHAR(150) NOT NULL,
    message    TEXT NOT NULL,
    type       VARCHAR(15) DEFAULT 'system'
                   CHECK (type IN ('order','booking','promo','system')),
    is_read    SMALLINT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS reviews (
    review_id  SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    shop_id    INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    order_id   INT,
    rating     SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS cart (
    cart_id    SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    product_id INT NOT NULL REFERENCES products(product_id) ON DELETE CASCADE,
    shop_id    INT NOT NULL REFERENCES shops(shop_id) ON DELETE CASCADE,
    quantity   INT DEFAULT 1,
    added_at   TIMESTAMPTZ DEFAULT NOW()
);

-- Triggers
CREATE OR REPLACE FUNCTION fn_reduce_stock()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE products SET stock = stock - NEW.quantity
    WHERE product_id = NEW.product_id;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_reduce_stock ON order_items;
CREATE TRIGGER trg_reduce_stock
AFTER INSERT ON order_items
FOR EACH ROW EXECUTE FUNCTION fn_reduce_stock();

CREATE OR REPLACE FUNCTION fn_update_rating()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE shops
    SET rating = (SELECT AVG(rating) FROM reviews WHERE shop_id = NEW.shop_id)
    WHERE shop_id = NEW.shop_id;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_update_rating ON reviews;
CREATE TRIGGER trg_update_rating
AFTER INSERT ON reviews
FOR EACH ROW EXECUTE FUNCTION fn_update_rating();

CREATE OR REPLACE FUNCTION fn_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at = NOW(); RETURN NEW; END;
$$;

DROP TRIGGER IF EXISTS trg_orders_updated_at ON orders;
CREATE TRIGGER trg_orders_updated_at
BEFORE UPDATE ON orders
FOR EACH ROW EXECUTE FUNCTION fn_updated_at();

-- View
CREATE OR REPLACE VIEW v_order_details AS
SELECT o.order_id, o.status, o.total_amount, o.created_at,
       u.name AS customer_name, u.phone AS customer_phone,
       s.name AS shop_name, s.category AS shop_category,
       o.delivery_address, o.payment_method, o.payment_status
FROM orders o
JOIN users u ON o.user_id = u.user_id
JOIN shops s ON o.shop_id = s.shop_id;
