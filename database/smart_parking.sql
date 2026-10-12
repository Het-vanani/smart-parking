-- ============================================================
-- Smart Parking Slot Booking System - Database Schema
-- DB: smart_parking_db | Engine: InnoDB | Charset: utf8mb4
-- Users are seeded via setup.php (bcrypt hashes generated live)
-- ============================================================

CREATE DATABASE IF NOT EXISTS smart_parking_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE smart_parking_db;

-- ─── USERS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(120)    NOT NULL,
  email         VARCHAR(180)    NOT NULL,
  phone         CHAR(10)        NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── PARKING SLOTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS parking_slots (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  slot_code   VARCHAR(10)     NOT NULL,
  zone        VARCHAR(30)     NOT NULL,
  slot_type   ENUM('car','bike','ev') NOT NULL,
  hourly_rate DECIMAL(8,2)   NOT NULL,
  status      ENUM('active','maintenance') NOT NULL DEFAULT 'active',
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slot_code (slot_code),
  KEY idx_zone_type (zone, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── BOOKINGS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
  id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED    NOT NULL,
  slot_id        INT UNSIGNED    NOT NULL,
  vehicle_number VARCHAR(20)     NOT NULL,
  vehicle_type   ENUM('car','bike','ev') NOT NULL,
  start_time     DATETIME        NOT NULL,
  end_time       DATETIME        NOT NULL,
  total_amount   DECIMAL(10,2)  NOT NULL,
  status         ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at   DATETIME        NULL DEFAULT NULL,
  PRIMARY KEY (id),
  -- Index speeds up the overlap-check query
  KEY idx_slot_times (slot_id, start_time, end_time),
  KEY idx_user (user_id),
  CONSTRAINT fk_booking_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id)
    REFERENCES parking_slots (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA: 30 parking slots across 3 zones
-- Zone A: Cars  @ ₹40/hr  (10 slots, 1 maintenance)
-- Zone B: Bikes @ ₹20/hr  (10 slots, 1 maintenance)
-- Zone C: EV   @ ₹60/hr  (10 slots, 1 maintenance)
-- ============================================================
INSERT INTO parking_slots (slot_code, zone, slot_type, hourly_rate, status) VALUES
-- Zone A - Cars
('A-01', 'Zone A', 'car', 40.00, 'active'),
('A-02', 'Zone A', 'car', 40.00, 'active'),
('A-03', 'Zone A', 'car', 40.00, 'active'),
('A-04', 'Zone A', 'car', 40.00, 'active'),
('A-05', 'Zone A', 'car', 40.00, 'active'),
('A-06', 'Zone A', 'car', 40.00, 'active'),
('A-07', 'Zone A', 'car', 40.00, 'active'),
('A-08', 'Zone A', 'car', 40.00, 'active'),
('A-09', 'Zone A', 'car', 40.00, 'active'),
('A-10', 'Zone A', 'car', 40.00, 'maintenance'),
-- Zone B - Bikes
('B-01', 'Zone B', 'bike', 20.00, 'active'),
('B-02', 'Zone B', 'bike', 20.00, 'active'),
('B-03', 'Zone B', 'bike', 20.00, 'active'),
('B-04', 'Zone B', 'bike', 20.00, 'active'),
('B-05', 'Zone B', 'bike', 20.00, 'active'),
('B-06', 'Zone B', 'bike', 20.00, 'active'),
('B-07', 'Zone B', 'bike', 20.00, 'active'),
('B-08', 'Zone B', 'bike', 20.00, 'active'),
('B-09', 'Zone B', 'bike', 20.00, 'active'),
('B-10', 'Zone B', 'bike', 20.00, 'maintenance'),
-- Zone C - EV
('C-01', 'Zone C', 'ev', 60.00, 'active'),
('C-02', 'Zone C', 'ev', 60.00, 'active'),
('C-03', 'Zone C', 'ev', 60.00, 'active'),
('C-04', 'Zone C', 'ev', 60.00, 'active'),
('C-05', 'Zone C', 'ev', 60.00, 'active'),
('C-06', 'Zone C', 'ev', 60.00, 'active'),
('C-07', 'Zone C', 'ev', 60.00, 'active'),
('C-08', 'Zone C', 'ev', 60.00, 'active'),
('C-09', 'Zone C', 'ev', 60.00, 'active'),
('C-10', 'Zone C', 'ev', 60.00, 'maintenance');

-- NOTE: Sample bookings (linked to seeded users) are inserted
-- by setup.php after it creates the user rows with real bcrypt hashes.
