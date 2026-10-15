-- CreateEnum
CREATE TYPE "public"."FlowerKind" AS ENUM ('FLOWER', 'GREEN');

-- CreateEnum
CREATE TYPE "public"."FlowerUnit" AS ENUM ('STEM', 'PIECE');

-- CreateEnum
CREATE TYPE "public"."BouquetFormat" AS ENUM ('BOUQUET', 'BAG', 'HATBOX', 'BASKET');

-- CreateEnum
CREATE TYPE "public"."SizeCode" AS ENUM ('S', 'M', 'L');

-- CreateEnum
CREATE TYPE "public"."OrderStatus" AS ENUM ('NEW', 'CONFIRMED', 'PHOTO_SENT', 'ON_THE_WAY', 'DONE', 'CANCELLED');

-- CreateEnum
CREATE TYPE "public"."DeliveryMethod" AS ENUM ('DELIVERY', 'PICKUP');

-- CreateEnum
CREATE TYPE "public"."PaymentMethod" AS ENUM ('CARD', 'ERIP', 'VIBER_LINK');

-- CreateTable
CREATE TABLE "public"."Flower" (
    "id" SERIAL NOT NULL,
    "name" TEXT NOT NULL,
    "kind" "public"."FlowerKind" NOT NULL DEFAULT 'FLOWER',
    "unit" "public"."FlowerUnit" NOT NULL DEFAULT 'STEM',
    "priceKop" INTEGER NOT NULL,
    "prevPriceKop" INTEGER,
    "inStock" BOOLEAN NOT NULL DEFAULT true,
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Flower_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Collection" (
    "id" SERIAL NOT NULL,
    "slug" TEXT NOT NULL,
    "name" TEXT NOT NULL,

    CONSTRAINT "Collection_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Bouquet" (
    "id" SERIAL NOT NULL,
    "slug" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "description" TEXT NOT NULL DEFAULT '',
    "format" "public"."BouquetFormat" NOT NULL DEFAULT 'BOUQUET',
    "packagingKop" INTEGER NOT NULL DEFAULT 0,
    "enabled" BOOLEAN NOT NULL DEFAULT true,
    "isHit" BOOLEAN NOT NULL DEFAULT false,
    "occasions" TEXT[],
    "photos" TEXT[],
    "sortOrder" INTEGER NOT NULL DEFAULT 0,
    "collectionId" INTEGER,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Bouquet_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."BouquetSize" (
    "id" SERIAL NOT NULL,
    "bouquetId" INTEGER NOT NULL,
    "code" "public"."SizeCode" NOT NULL,
    "note" TEXT NOT NULL DEFAULT '',

    CONSTRAINT "BouquetSize_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."BouquetItem" (
    "id" SERIAL NOT NULL,
    "sizeId" INTEGER NOT NULL,
    "flowerId" INTEGER NOT NULL,
    "qty" INTEGER NOT NULL,

    CONSTRAINT "BouquetItem_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Addon" (
    "id" SERIAL NOT NULL,
    "code" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "priceKop" INTEGER NOT NULL DEFAULT 0,
    "included" BOOLEAN NOT NULL DEFAULT false,
    "active" BOOLEAN NOT NULL DEFAULT true,
    "sortOrder" INTEGER NOT NULL DEFAULT 0,

    CONSTRAINT "Addon_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Order" (
    "id" SERIAL NOT NULL,
    "number" INTEGER NOT NULL,
    "status" "public"."OrderStatus" NOT NULL DEFAULT 'NEW',
    "customerName" TEXT NOT NULL,
    "customerPhone" TEXT NOT NULL,
    "recipientName" TEXT NOT NULL DEFAULT '',
    "recipientPhone" TEXT NOT NULL DEFAULT '',
    "callRecipient" BOOLEAN NOT NULL DEFAULT false,
    "anonymous" BOOLEAN NOT NULL DEFAULT false,
    "cardText" TEXT NOT NULL DEFAULT '',
    "comment" TEXT NOT NULL DEFAULT '',
    "deliveryMethod" "public"."DeliveryMethod" NOT NULL,
    "address" TEXT NOT NULL DEFAULT '',
    "shop" TEXT NOT NULL,
    "deliveryZone" INTEGER,
    "distanceKm" DOUBLE PRECISION,
    "deliveryDate" DATE NOT NULL,
    "deliverySlot" TEXT NOT NULL,
    "paymentMethod" "public"."PaymentMethod" NOT NULL,
    "paid" BOOLEAN NOT NULL DEFAULT false,
    "itemsKop" INTEGER NOT NULL,
    "discountKop" INTEGER NOT NULL DEFAULT 0,
    "deliveryKop" INTEGER NOT NULL DEFAULT 0,
    "totalKop" INTEGER NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" TIMESTAMP(3) NOT NULL,

    CONSTRAINT "Order_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."OrderItem" (
    "id" SERIAL NOT NULL,
    "orderId" INTEGER NOT NULL,
    "bouquetId" INTEGER,
    "addonId" INTEGER,
    "name" TEXT NOT NULL,
    "size" TEXT NOT NULL DEFAULT '',
    "composition" TEXT NOT NULL DEFAULT '',
    "qty" INTEGER NOT NULL,
    "priceKop" INTEGER NOT NULL,

    CONSTRAINT "OrderItem_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Reminder" (
    "id" SERIAL NOT NULL,
    "name" TEXT NOT NULL,
    "phone" TEXT NOT NULL,
    "occasion" TEXT NOT NULL,
    "date" DATE NOT NULL,
    "createdAt" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT "Reminder_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."Setting" (
    "key" TEXT NOT NULL,
    "value" JSONB NOT NULL,

    CONSTRAINT "Setting_pkey" PRIMARY KEY ("key")
);

-- CreateIndex
CREATE UNIQUE INDEX "Flower_name_key" ON "public"."Flower"("name");

-- CreateIndex
CREATE UNIQUE INDEX "Collection_slug_key" ON "public"."Collection"("slug");

-- CreateIndex
CREATE UNIQUE INDEX "Bouquet_slug_key" ON "public"."Bouquet"("slug");

-- CreateIndex
CREATE UNIQUE INDEX "BouquetSize_bouquetId_code_key" ON "public"."BouquetSize"("bouquetId", "code");

-- CreateIndex
CREATE UNIQUE INDEX "BouquetItem_sizeId_flowerId_key" ON "public"."BouquetItem"("sizeId", "flowerId");

-- CreateIndex
CREATE UNIQUE INDEX "Addon_code_key" ON "public"."Addon"("code");

-- CreateIndex
CREATE UNIQUE INDEX "Order_number_key" ON "public"."Order"("number");

-- AddForeignKey
ALTER TABLE "public"."Bouquet" ADD CONSTRAINT "Bouquet_collectionId_fkey" FOREIGN KEY ("collectionId") REFERENCES "public"."Collection"("id") ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."BouquetSize" ADD CONSTRAINT "BouquetSize_bouquetId_fkey" FOREIGN KEY ("bouquetId") REFERENCES "public"."Bouquet"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."BouquetItem" ADD CONSTRAINT "BouquetItem_sizeId_fkey" FOREIGN KEY ("sizeId") REFERENCES "public"."BouquetSize"("id") ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."BouquetItem" ADD CONSTRAINT "BouquetItem_flowerId_fkey" FOREIGN KEY ("flowerId") REFERENCES "public"."Flower"("id") ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE "public"."OrderItem" ADD CONSTRAINT "OrderItem_orderId_fkey" FOREIGN KEY ("orderId") REFERENCES "public"."Order"("id") ON DELETE CASCADE ON UPDATE CASCADE;
