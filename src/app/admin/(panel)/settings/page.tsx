import { db } from "@/lib/db";
import { getSetting } from "@/lib/settings";
import { SettingsEditor } from "./SettingsEditor";

export default async function Settings() {
  const [shop, delivery, districts, addons] = await Promise.all([
    getSetting("shop"),
    getSetting("delivery"),
    getSetting("districts"),
    db.addon.findMany({ orderBy: { sortOrder: "asc" } }),
  ]);
  const bouquets = await db.bouquet.findMany({ where: { enabled: true }, select: { slug: true, name: true }, orderBy: { name: "asc" } });
  return (
    <SettingsEditor
      shop={shop}
      delivery={delivery}
      districts={districts}
      bouquets={bouquets}
      addons={addons.map((a) => ({ id: a.id, code: a.code, name: a.name, priceKop: a.priceKop, included: a.included, active: a.active }))}
    />
  );
}
