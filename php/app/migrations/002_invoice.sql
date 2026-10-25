-- Безналичный расчёт для организаций (решение владельца 2026-10-25): способ оплаты INVOICE и реквизиты компании.
-- company — JSON: name, unp, address, iban, bank, bic, email (см. Checkout::parseCompany); у остальных заказов NULL.
ALTER TABLE orders
  MODIFY paymentMethod ENUM('CARD','ERIP','VIBER_LINK','INVOICE') NOT NULL,
  ADD COLUMN company TEXT NULL AFTER paymentMethod;
