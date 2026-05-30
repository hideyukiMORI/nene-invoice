-- Schema snapshot for the login_attempts table (SQLite dialect, used by repository tests).
-- Production DDL is applied via database/migrations/ (Phinx). Keep this in sync.
-- Records failed login attempts per source IP for throttling (security: F-2).
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts (ip_address, created_at);
