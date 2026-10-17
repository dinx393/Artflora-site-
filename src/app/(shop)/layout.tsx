import { CartProvider } from "@/components/CartProvider";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { contactLinks } from "@/lib/contacts";
import { minDeliveryKop } from "@/lib/delivery";
import { getSetting } from "@/lib/settings";

export const dynamic = "force-dynamic";

export default async function ShopLayout({ children }: { children: React.ReactNode }) {
  const [shop, delivery] = await Promise.all([getSetting("shop"), getSetting("delivery")]);
  return (
    <CartProvider>
      <Header phone={shop.phone} contacts={contactLinks(shop)} minDeliveryKop={minDeliveryKop(delivery)} pickupPct={delivery.pickupDiscountPct} />
      <main>{children}</main>
      <Footer shop={shop} shops={delivery.shops} />
    </CartProvider>
  );
}
