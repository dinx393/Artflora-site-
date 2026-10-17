-- Защита от дублей заказов, повторы уведомлений в Telegram, индексы для списка заказов.
ALTER TABLE "Order" ADD COLUMN "idempotencyKey" TEXT;
ALTER TABLE "Order" ADD COLUMN "notifiedAt" TIMESTAMP(3);
ALTER TABLE "Order" ADD COLUMN "notifyAttempts" INTEGER NOT NULL DEFAULT 0;
ALTER TABLE "Order" ADD COLUMN "notifyError" TEXT NOT NULL DEFAULT '';
CREATE UNIQUE INDEX "Order_idempotencyKey_key" ON "Order"("idempotencyKey");
CREATE INDEX "Order_status_deliveryDate_idx" ON "Order"("status", "deliveryDate");
CREATE INDEX "Order_deliveryDate_idx" ON "Order"("deliveryDate");
CREATE INDEX "Order_createdAt_idx" ON "Order"("createdAt");
