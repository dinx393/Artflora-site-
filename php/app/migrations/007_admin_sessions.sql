-- Входы в админку: в cookie случайный ключ, здесь — его отпечаток. Выход удаляет строку,
-- поэтому скопированная cookie после выхода (или после смены admin_password в config.php) не работает.
CREATE TABLE admin_sessions (
  id CHAR(64) PRIMARY KEY,           -- sha256 ключа из cookie
  passwordMark CHAR(22) NOT NULL,    -- подпись пароля: сменили admin_password — все входы недействительны
  expiresAt BIGINT NOT NULL,         -- миллисекунды
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  userAgent VARCHAR(200) NOT NULL DEFAULT '',
  KEY admin_sessions_exp (expiresAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
