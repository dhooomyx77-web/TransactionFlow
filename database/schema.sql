CREATE DATABASE IF NOT EXISTS transactionflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE transactionflow;
CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
 failed_attempts INT NOT NULL DEFAULT 0,
 locked_until DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS transactions (
 id INT AUTO_INCREMENT PRIMARY KEY,
 transaction_number VARCHAR(30) NOT NULL UNIQUE,
 applicant_name VARCHAR(100) NOT NULL,
 transaction_type VARCHAR(100) NOT NULL,
 status ENUM('new','under_review','incomplete','completed') NOT NULL DEFAULT 'new',
 notes TEXT NULL,
 version INT NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX(status), INDEX(applicant_name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_log (
 id INT AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 action VARCHAR(30) NOT NULL,
 transaction_id INT NULL,
 details TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB;
