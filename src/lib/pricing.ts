// Цена размера = Σ (розничная цена цветка × количество) + упаковка этого размера.
// Размер доступен, только если все его цветы есть в наличии или для них есть замена в наличии.
// Количество бывает дробным (1,5 ветки), цена позиции округляется до копейки.
// Букет показываем, если он включён и у него остался хотя бы один доступный размер.

// substitutes — чем можно заменить цветок, если его нет (задаётся в админке «Цветы»)
export type PricedFlower = { id: number; name: string; priceKop: number; inStock: boolean; substitutes?: { name: string; inStock: boolean }[] };
export type PricedItem = { qty: number; flower: PricedFlower };
export type PricedSize = { code: SizeCode; note: string; packagingKop: number; items: PricedItem[] };
export type PricedBouquet = { enabled: boolean; sizes: PricedSize[] };

// Размеры по порядку. Незаполненные (без цветов) на сайте не показываются.
export const SIZE_ORDER = ["XS", "S", "M", "L", "XL", "XXL"] as const;
export type SizeCode = (typeof SIZE_ORDER)[number];

export function itemKop(i: PricedItem): number {
  return Math.round(i.flower.priceKop * i.qty);
}

export function flowersKop(items: PricedItem[]): number {
  return items.reduce((sum, i) => sum + itemKop(i), 0);
}

// Чем собираем: сам цветок, если есть; иначе первая замена в наличии; null — собрать нечем.
// Цена при замене остаётся ценой исходного цветка, чтобы цена букета не прыгала.
export function replacementFor(f: Pick<PricedFlower, "inStock" | "substitutes">): string | null | undefined {
  if (f.inStock) return undefined;
  return f.substitutes?.find((x) => x.inStock)?.name ?? null;
}

export function sizePriceKop(size: Pick<PricedSize, "items" | "packagingKop">): number {
  return flowersKop(size.items) + size.packagingKop;
}

export function missingFlowers(size: PricedSize): string[] {
  return size.items.filter((i) => replacementFor(i.flower) === null).map((i) => i.flower.name);
}

export function sizeAvailable(size: PricedSize): boolean {
  return size.items.length > 0 && missingFlowers(size).length === 0;
}

export function sortSizes<T extends { code: string }>(sizes: T[]): T[] {
  return [...sizes].sort(
    (a, b) => SIZE_ORDER.indexOf(a.code as never) - SIZE_ORDER.indexOf(b.code as never),
  );
}

export function availableSizes<T extends PricedSize>(b: { sizes: T[] }): T[] {
  return sortSizes(b.sizes.filter(sizeAvailable));
}

// Размер «по умолчанию» — тот, что на фото (владелец отмечает в админке), иначе M, иначе первый доступный.
export function defaultSize<T extends PricedSize>(b: { sizes: T[]; photoSize?: string }): T | undefined {
  const av = availableSizes(b);
  return av.find((s) => s.code === b.photoSize) ?? av.find((s) => s.code === "M") ?? av[0];
}

export type Visibility =
  | { visible: true }
  | { visible: false; reason: "disabled" }
  | { visible: false; reason: "no-flowers"; missing: string[] };

export function bouquetVisibility(b: PricedBouquet): Visibility {
  if (!b.enabled) return { visible: false, reason: "disabled" };
  if (availableSizes(b).length > 0) return { visible: true };
  const missing = new Set<string>();
  for (const s of b.sizes) missingFlowers(s).forEach((m) => missing.add(m));
  return { visible: false, reason: "no-flowers", missing: [...missing] };
}

// 1.5 → «1,5»
export function qtyText(q: number): string {
  return String(Math.round(q * 100) / 100).replace(".", ",");
}

export function compositionText(items: { qty: number; flower: { name: string; inStock?: boolean; substitutes?: { name: string; inStock: boolean }[] } }[]): string {
  return items
    .map((i) => {
      const sub = i.flower.inStock === false ? replacementFor({ inStock: false, substitutes: i.flower.substitutes }) : undefined;
      return sub ? `${sub} (вместо: ${i.flower.name.toLowerCase()}) × ${qtyText(i.qty)}` : `${i.flower.name} × ${qtyText(i.qty)}`;
    })
    .join(", ");
}

// Сколько всего цветков в размере, для подписи «11 цветков» (зелень не считаем).
export function flowerCount(items: { qty: number; flower: { kind?: string } }[]): number {
  return Math.round(items.filter((i) => i.flower.kind !== "GREEN").reduce((s, i) => s + i.qty, 0));
}
