import { db } from "@/lib/db";
import { FlowersEditor } from "./FlowersEditor";

export default async function Flowers() {
  const [flowers, bouquets] = await Promise.all([
    db.flower.findMany({ orderBy: [{ sortOrder: "asc" }, { name: "asc" }], include: { substitutes: { select: { id: true } } } }), // порядок задаёт владелец перетаскиванием
    db.bouquet.findMany({ include: { sizes: { include: { items: true } } }, orderBy: { id: "asc" } }),
  ]);
  return (
    <FlowersEditor
      initial={flowers.map((f) => ({ id: f.id, name: f.name, kind: f.kind, unit: f.unit, priceKop: f.priceKop, prevPriceKop: f.prevPriceKop, inStock: f.inStock, substitutes: f.substitutes.map((x) => x.id) }))}
      bouquets={bouquets.map((b) => ({
        id: b.id,
        name: b.name,
        enabled: b.enabled,
        sizes: b.sizes.map((s) => ({ code: s.code, packagingKop: s.packagingKop, items: s.items.map((i) => ({ flowerId: i.flowerId, qty: i.qty })) })),
      }))}
    />
  );
}
