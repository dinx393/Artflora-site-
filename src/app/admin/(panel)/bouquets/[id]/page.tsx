import { notFound } from "next/navigation";
import { db } from "@/lib/db";
import { BouquetEditor } from "./BouquetEditor";

export default async function EditBouquet({ params }: { params: Promise<{ id: string }> }) {
  const id = Number((await params).id);
  if (!Number.isInteger(id)) notFound();
  const [b, flowers, collections] = await Promise.all([
    db.bouquet.findUnique({ where: { id }, include: { sizes: { include: { items: { orderBy: { id: "asc" } } } } } }),
    db.flower.findMany({ orderBy: [{ kind: "asc" }, { sortOrder: "asc" }, { name: "asc" }] }),
    db.collection.findMany({ orderBy: { name: "asc" } }),
  ]);
  if (!b) notFound();
  const siblings = b.collectionId
    ? await db.bouquet.findMany({ where: { collectionId: b.collectionId }, select: { id: true, name: true, format: true } })
    : [];

  return (
    <BouquetEditor
      initial={{
        id: b.id,
        name: b.name,
        slug: b.slug,
        description: b.description,
        format: b.format,
        packagingKop: b.packagingKop,
        enabled: b.enabled,
        isHit: b.isHit,
        occasions: b.occasions,
        photos: b.photos,
        collectionId: b.collectionId,
        sizes: (["S", "M", "L"] as const).map((code) => {
          const s = b.sizes.find((x) => x.code === code);
          return { code, note: s?.note ?? "", items: s?.items.map((i) => ({ flowerId: i.flowerId, qty: i.qty })) ?? [] };
        }),
      }}
      flowers={flowers.map((f) => ({ id: f.id, name: f.name, kind: f.kind, unit: f.unit, priceKop: f.priceKop, inStock: f.inStock }))}
      collections={collections.map((c) => ({ id: c.id, name: c.name }))}
      siblings={siblings}
    />
  );
}
