-- «Финансы» → доходы и расходы по банку (решение владельца 2026-11-15): вносятся цифрами из банкинга —
-- сколько заплатили поставщику, сколько пришло эквайринга и т. п. Описание — откуда пришло / куда ушло.
CREATE TABLE money_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entryDate DATE NOT NULL,
  direction ENUM('IN','OUT') NOT NULL,
  category VARCHAR(30) NOT NULL,
  shop VARCHAR(20) NOT NULL DEFAULT '',   -- '' — общее, не магазину
  amountKop INT NOT NULL,
  description VARCHAR(300) NOT NULL DEFAULT '',
  createdBy VARCHAR(80) NOT NULL DEFAULT '',
  createdAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY me_date (entryDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
