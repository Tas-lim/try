CREATE DATABASE IF NOT EXISTS sil
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sil;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX users_role_idx (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX contact_messages_email_idx (email),
    INDEX contact_messages_created_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(80) NOT NULL UNIQUE,
    user_id INT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    topic VARCHAR(120) NOT NULL DEFAULT 'General project',
    status ENUM('open', 'waiting_customer', 'closed') NOT NULL DEFAULT 'open',
    last_message_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT conversations_user_fk
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX conversations_user_idx (user_id),
    INDEX conversations_email_idx (email),
    INDEX conversations_status_idx (status),
    INDEX conversations_last_message_idx (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_type ENUM('customer', 'admin', 'system') NOT NULL,
    sender_user_id INT NULL,
    body TEXT NOT NULL,
    media_type ENUM('text', 'voice', 'audio', 'video') NOT NULL DEFAULT 'text',
    media_path VARCHAR(255) NULL,
    media_name VARCHAR(180) NULL,
    media_size INT NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT conversation_messages_conversation_fk
        FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        ON DELETE CASCADE,
    CONSTRAINT conversation_messages_user_fk
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE SET NULL,
    INDEX conversation_messages_conversation_idx (conversation_id, created_at),
    INDEX conversation_messages_sender_idx (sender_type),
    INDEX conversation_messages_read_idx (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX password_resets_email_idx (email),
    INDEX password_resets_expires_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
