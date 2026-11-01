-- Предзаказ (решение владельца 2026-11-01): галочка «Предзаказ» у букета — он в разделе /predzakaz, даже если цветов сейчас нет
-- (закупим к дате). В заказе пометка: BUY — закупить цветы под заказ, STOCK — закупка на дату уже сделана, клиент предупреждён.
ALTER TABLE bouquets ADD COLUMN isPreorder TINYINT(1) NOT NULL DEFAULT 0 AFTER isPinned;
ALTER TABLE orders ADD COLUMN preorder VARCHAR(10) NULL;
-- Цена цветов к дате может измениться — покупатель сразу выбирает: COPY — точная копия, даже если выйдет дороже;
-- BUDGET — сохранить сегодняшнюю сумму, флорист подстроит состав.
ALTER TABLE orders ADD COLUMN preorderChoice VARCHAR(10) NULL;
