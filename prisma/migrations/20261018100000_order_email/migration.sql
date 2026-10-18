-- Письмо о заказе на почту магазина
ALTER TABLE "Order" ADD COLUMN "emailedAt" TIMESTAMP(3),
ADD COLUMN "emailAttempts" INTEGER NOT NULL DEFAULT 0,
ADD COLUMN "emailError" TEXT NOT NULL DEFAULT '';
