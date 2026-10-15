import "server-only";
import type { Prisma } from "@prisma/client";
import { db } from "./db";
import {
  availableSizes,
  bouquetVisibility,
  compositionText,
  defaultSize,
  flowerCount,
  sizePriceKop,
  sortSizes,
} from "./pricing";

export const bouquetInclude = {
  collection: true,
  sizes: { include: { items: { include: { flower: true }, orderBy: { id: "asc" } } } },
} satisfies Prisma.BouquetInclude;

export type BouquetRow = Prisma.BouquetGetPayload<{ include: typeof bouquetInclude }>;

export type SizeView = {
  code: "S" | "M" | "L";
  note: string;
  priceKop: number;
  available: boolean;
  composition: string;
  flowers: number;
};

export type BouquetView = {
  id: number;
  slug: string;
  name: string;
  description: string;
  format: BouquetRow["format"];
  isHit: boolean;
  occasions: string[];
  photos: string[];
  collectionId: number | null;
  collectionSlug: string | null;
  collectionName: string | null;
  flowerNames: string[];
  sizes: SizeView[]; // только доступные, по порядку S, M, L
  defaultSize: "S" | "M" | "L";
  priceKop: number; // цена размера по умолчанию
};

// Готовим букет для витрины. Невидимые букеты → null.
export function toView(b: BouquetRow): BouquetView | null {
  if (!bouquetVisibility(b).visible) return null;
  const sizes = availableSizes(b).map<SizeView>((s) => ({
    code: s.code,
    note: s.note,
    priceKop: sizePriceKop(s, b.packagingKop),
    available: true,
    composition: compositionText(s.items),
    flowers: flowerCount(s.items),
  }));
  const def = defaultSize(b)!;
  const names = new Set<string>();
  sortSizes(b.sizes).forEach((s) => s.items.forEach((i) => names.add(i.flower.name)));
  return {
    id: b.id,
    slug: b.slug,
    name: b.name,
    description: b.description,
    format: b.format,
    isHit: b.isHit,
    occasions: b.occasions,
    photos: b.photos.length ? b.photos : ["/demo/placeholder.svg"],
    collectionId: b.collectionId,
    collectionSlug: b.collection?.slug ?? null,
    collectionName: b.collection?.name ?? null,
    flowerNames: [...names],
    sizes,
    defaultSize: def.code,
    priceKop: sizePriceKop(def, b.packagingKop),
  };
}

export async function visibleBouquets(where: Prisma.BouquetWhereInput = {}): Promise<BouquetView[]> {
  const rows = await db.bouquet.findMany({
    where: { enabled: true, ...where },
    include: bouquetInclude,
    orderBy: [{ sortOrder: "asc" }, { id: "asc" }],
  });
  return rows.map(toView).filter((b): b is BouquetView => b !== null);
}

export async function bouquetBySlug(slug: string): Promise<BouquetView | null> {
  const row = await db.bouquet.findUnique({ where: { slug }, include: bouquetInclude });
  return row ? toView(row) : null;
}

export type CatalogFilter = {
  occasion?: string;
  price?: { min: number; max: number };
  flower?: string;
  format?: string;
  collection?: string;
  q?: string;
};

export function filterBouquets(list: BouquetView[], f: CatalogFilter): BouquetView[] {
  const q = f.q?.trim().toLowerCase();
  return list.filter((b) => {
    if (f.occasion && !b.occasions.includes(f.occasion)) return false;
    if (f.price && (b.priceKop < f.price.min || b.priceKop >= f.price.max)) return false;
    if (f.flower && !b.flowerNames.some((n) => n.toLowerCase().includes(f.flower!.toLowerCase())))
      return false;
    if (f.format && b.format !== f.format) return false;
    if (f.collection && b.collectionSlug !== f.collection) return false;
    if (q) {
      const hay = [b.name, b.description, ...b.flowerNames].join(" ").toLowerCase();
      // «розы» должно находить «роза»: сравниваем по основе слова
      const words = q.split(/\s+/).map((w) => (w.length > 4 ? w.slice(0, -1) : w));
      if (!words.every((w) => hay.includes(w))) return false;
    }
    return true;
  });
}
