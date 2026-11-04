-- RentEase schema (MySQL 5.7+/8, MariaDB 10.3+). Import in phpMyAdmin, then import seed.sql.
CREATE DATABASE IF NOT EXISTS rentease_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rentease_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts, password_resets, bookings, vehicles, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  licence_number VARCHAR(30)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  city           VARCHAR(80)  NOT NULL DEFAULT '',
  licence_type   VARCHAR(20)  NOT NULL DEFAULT 'LMV',
  phone          VARCHAR(20)  NOT NULL DEFAULT '',
  dob            DATE NULL,                      -- used for vehicle min_age checks
  role           ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  status         ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_licence (licence_number),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name                VARCHAR(120) NOT NULL,
  description         TEXT NULL,
  category            VARCHAR(40)  NOT NULL,
  photo               VARCHAR(80)  NULL,
  reg_number          VARCHAR(30)  NOT NULL,
  fuel_type           VARCHAR(20)  NOT NULL,
  transmission        VARCHAR(20)  NOT NULL DEFAULT 'Manual',
  seats               TINYINT UNSIGNED NOT NULL DEFAULT 4,
  rent_per_day        DECIMAL(10,2) NOT NULL,
  pickup_location     VARCHAR(150) NOT NULL,
  branch              VARCHAR(100) NOT NULL,
  available_from      DATE NOT NULL,
  available_until     DATE NOT NULL,
  eligibility         VARCHAR(255) NOT NULL DEFAULT 'Any valid driving licence',
  min_age             TINYINT UNSIGNED NOT NULL DEFAULT 18,
  licence_restriction VARCHAR(100) NOT NULL DEFAULT '',   -- comma list e.g. "LMV,HMV"; empty = any
  branch_contact      VARCHAR(60)  NOT NULL DEFAULT '',
  cancel_policy       VARCHAR(20)  NOT NULL DEFAULT 'flexible', -- flexible|moderate|strict|none
  status              ENUM('draft','published','booking_closed','rented_out','maintenance','archived') NOT NULL DEFAULT 'draft',
  created_by          INT UNSIGNED NOT NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vehicles_reg (reg_number),
  KEY idx_vehicles_status (status),
  KEY idx_vehicles_category (category),
  CONSTRAINT fk_vehicles_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code  VARCHAR(30) NOT NULL,
  vehicle_id    INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  pickup_dt     DATETIME NOT NULL,
  return_dt     DATETIME NOT NULL,
  total_rent    DECIMAL(10,2) NOT NULL,
  status        ENUM('confirmed','waitlisted','cancelled') NOT NULL DEFAULT 'confirmed',
  return_status ENUM('pending','returned','overdue') NOT NULL DEFAULT 'pending',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_booking_code (booking_code),
  UNIQUE KEY uq_vehicle_user_pickup (vehicle_id, user_id, pickup_dt),
  KEY idx_bookings_vehicle (vehicle_id, status, pickup_dt, return_dt),
  KEY idx_bookings_user (user_id),
  CONSTRAINT fk_bookings_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_reset_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support table for the 5-failures / 5-minutes login lockout (success: 0=fail, 2=lock marker)
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(150) NOT NULL,
  ip           VARCHAR(45)  NOT NULL,
  success      TINYINT NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempts (identifier, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
