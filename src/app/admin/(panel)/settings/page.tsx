import { db } from "@/lib/db";
import { smtpConfigured } from "@/lib/notify";
import { getSetting } from "@/lib/settings";
import { SettingsEditor } from "./SettingsEditor";

export default async function Settings() {
  const [shop, addons, colors] = await Promise.all([
    getSetting("shop"),
    db.addon.findMany({ orderBy: { sortOrder: "asc" } }),
    getSetting("colors"),
  ]);
  const bouquets = await db.bouquet.findMany({ where: { enabled: true }, select: { slug: true, name: true }, orderBy: { name: "asc" } });
  return (
    <SettingsEditor
      shop={shop}
      bouquets={bouquets}
      colors={colors}
      smtpReady={smtpConfigured()}
      addons={addons.map((a) => ({ id: a.id, code: a.code, name: a.name, priceKop: a.priceKop, included: a.included, active: a.active }))}
    />
  );
}
