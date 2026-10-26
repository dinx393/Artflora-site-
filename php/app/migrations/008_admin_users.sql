-- Сотрудники с отдельными логинами (решение владельца 2026-10-26). Старший администратор может всё,
-- обычный — только разрешённые разделы (Auth::STAFF). Вход паролем из config.php — тоже старший администратор.
CREATE TABLE admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  role VARCHAR(10) NOT NULL DEFAULT 'staff',  -- owner | staff
  active TINYINT(1) NOT NULL DEFAULT 1,
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastLoginAt DATETIME NULL,
  UNIQUE KEY admin_users_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE admin_sessions ADD COLUMN userId INT NULL AFTER id, ADD KEY admin_sessions_user (userId);
