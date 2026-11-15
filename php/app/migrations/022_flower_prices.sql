-- История розничных цен цветов (решение владельца 2026-11-15): «Продано на» в «Финансах» считается
-- по цене, которая действовала в день накладной / списания. До 15.11.2026 истории нет — берётся цена на этот день.
CREATE TABLE flower_prices (
  flowerId INT NOT NULL,
  fromDate DATE NOT NULL,
  priceKop INT NOT NULL,
  PRIMARY KEY (flowerId, fromDate),
  CONSTRAINT fk_flower_prices_flower FOREIGN KEY (flowerId) REFERENCES flowers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO flower_prices (flowerId, fromDate, priceKop) SELECT id, '2000-01-01', priceKop FROM flowers;
