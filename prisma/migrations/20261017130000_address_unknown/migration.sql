-- «Уточнить адрес у получателя»: клиент платит за букет, доставку доплачивает после уточнения.
ALTER TABLE "Order" ADD COLUMN "addressUnknown" BOOLEAN NOT NULL DEFAULT false;
