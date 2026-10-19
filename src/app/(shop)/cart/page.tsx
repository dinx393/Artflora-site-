import type { Metadata } from "next";
import { Checkout } from "@/components/Checkout";
import { districtOptions } from "@/lib/districts";
import { getSetting } from "@/lib/settings";

export const metadata: Metadata = { title: "Корзина", robots: { index: false } };

export default async function CartPage() {
  const [shop, delivery, list] = await Promise.all([getSetting("shop"), getSetting("delivery"), getSetting("districts")]);
  return (
    <Checkout
      shops={delivery.shops.map((s) => ({ code: s.code, name: s.name, address: s.address }))}
      pickupPct={delivery.pickupDiscountPct}
      freeFromKop={delivery.freeFromKop}
      maxOutsideKm={delivery.maxOutsideMkadKm}
      districts={districtOptions(list, delivery)}
      leadHours={shop.leadHours}
    />
  );
}
