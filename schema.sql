CREATE DATABASE IF NOT EXISTS budget_planner CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE budget_planner;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  type ENUM('income','expense') NOT NULL,
  budget_limit DECIMAL(10,2) NULL,
  UNIQUE KEY uq_cat (user_id, name, type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL CHECK (amount > 0),
  description VARCHAR(255) NOT NULL DEFAULT '',
  trans_date DATE NOT NULL,
  INDEX idx_user_date (user_id, trans_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;
