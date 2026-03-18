-- ============================================
-- Security Update: Brute-Force Lockout + Password Reset
-- TesdaBCAT v1.02 — Run this once on your database
-- ============================================

USE tesda_db;

-- Add brute-force lockout columns to users table
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0 AFTER last_login,
    ADD COLUMN IF NOT EXISTS lockout_until DATETIME NULL AFTER failed_attempts,
    ADD COLUMN IF NOT EXISTS session_start DATETIME NULL AFTER lockout_until,
    ADD COLUMN IF NOT EXISTS last_activity DATETIME NULL AFTER session_start;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_id    INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id     INT(11) NOT NULL,
    token       VARCHAR(100) NOT NULL UNIQUE,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP email settings for notifications
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('smtp_host',      'smtp.gmail.com', 'text',   'SMTP server hostname'),
('smtp_port',      '587',            'number', 'SMTP server port'),
('smtp_user',      '',               'text',   'SMTP email address'),
('smtp_pass',      '',               'text',   'SMTP email password or app password'),
('smtp_from_name', 'TESDA-BCAT GMS', 'text',   'Sender display name for email notifications'),
('email_notifications', '0',         'text',   'Enable email notifications (1=yes, 0=no)');
