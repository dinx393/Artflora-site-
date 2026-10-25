-- Оплата в магазине при получении (карта или наличные), только для самовывоза (решение владельца 2026-10-25).
ALTER TABLE orders MODIFY paymentMethod ENUM('CARD','ERIP','VIBER_LINK','INVOICE','SHOP') NOT NULL;
