import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { BuyBox } from "@/components/BuyBox";
import { DeliveryEstimate } from "@/components/DeliveryEstimate";
import { Gallery } from "@/components/Gallery";
import { ProductCard } from "@/components/ProductCard";
import { bouquetBySlug, visibleBouquets } from "@/lib/catalog";
import { db } from "@/lib/db";
import { formatByn } from "@/lib/money";
import { FORMATS, occasionLabel } from "@/lib/occasions";
import { getSetting } from "@/lib/settings";

type Params = { slug: string };

export async function generateMetadata({ params }: { params: Promise<Params> }): Promise<Metadata> {
  const b = await bouquetBySlug((await params).slug);
  if (!b) return { title: "Букет не найден" };
  return {
    title: `${b.name} — ${formatByn(b.priceKop)}`,
    description: b.description || `${FORMATS[b.format].label}: ${b.sizes.find((s) => s.code === b.defaultSize)?.composition}`,
    openGraph: { images: b.photos.slice(0, 1) },
  };
}

// «Розы» в тегах, если в составе есть розы
function flowerTags(names: string[]): string[] {
  const tags: string[] = [];
  if (names.some((n) => /роз/i.test(n))) tags.push("Розы");
  if (names.some((n) => /хризантем/i.test(n))) tags.push("Хризантемы");
  if (names.some((n) => /пион/i.test(n))) tags.push("Пионы");
  return tags;
}

export default async function BouquetPage({ params }: { params: Promise<Params> }) {
  const { slug } = await params;
  const b = await bouquetBySlug(slug);
  if (!b) notFound();

  const [shop, delivery, districts, addons, all] = await Promise.all([
    getSetting("shop"),
    getSetting("delivery"),
    getSetting("districts"),
    db.addon.findMany({ where: { active: true, code: { not: "card" } }, orderBy: { sortOrder: "asc" } }),
    visibleBouquets(),
  ]);

  const siblings = b.collectionId ? all.filter((x) => x.collectionId === b.collectionId) : [];
  const similar = all
    .filter((x) => x.id !== b.id && (!b.collectionId || x.collectionId !== b.collectionId))
    .map((x) => ({ x, score: x.occasions.filter((o) => b.occasions.includes(o)).length * 2 - Math.abs(x.priceKop - b.priceKop) / 5000 }))
    .sort((p, q) => q.score - p.score)
    .slice(0, 4)
    .map((p) => p.x);
  const def = b.sizes.find((s) => s.code === b.defaultSize)!;
  const firstOcc = b.occasions[0];
  const [nameMain, nameQuote] = b.name.includes("«") ? [b.name.slice(0, b.name.indexOf("«")), b.name.slice(b.name.indexOf("«"))] : [b.name, ""];

  return (
    <div className="wrap stack" style={{ paddingTop: 20, paddingBottom: 64, gap: 40 }}>
      <nav className="small muted row" style={{ gap: 8 }}>
        <Link href="/" className="nolink muted">Главная</Link>
        <span>/</span>
        <Link href="/catalog" className="nolink muted">Каталог</Link>
        {firstOcc && (
          <>
            <span>/</span>
            <Link href={`/catalog?occasion=${firstOcc}`} className="nolink muted">{occasionLabel(firstOcc)}</Link>
          </>
        )}
        <span>/</span>
        <span style={{ color: "var(--cherry)" }}>{b.name}</span>
      </nav>

      <div className="product">
        <Gallery photos={b.photos} name={b.name} hit={b.isHit} />

        <div className="buy">
          <div className="row" style={{ gap: 6 }}>
            {b.occasions.map((o) => (
              <Link key={o} href={`/catalog?occasion=${o}`} className="tag">{occasionLabel(o)}</Link>
            ))}
            {flowerTags(b.flowerNames).map((t) => (
              <Link key={t} href={`/catalog?q=${encodeURIComponent(t)}`} className="tag">{t}</Link>
            ))}
          </div>
          <h1>
            {nameMain}
            {nameQuote && <span className="script">{nameQuote}</span>}
          </h1>

          {siblings.length > 1 && (
            <div className="stack">
              <div className="row between" style={{ alignItems: "baseline" }}>
                <span className="label">Этот же состав в другом виде</span>
                {b.collectionSlug && (
                  <Link href={`/catalog?collection=${b.collectionSlug}`} className="small" style={{ fontWeight: 700, color: "var(--olive)" }}>
                    Вся коллекция «{b.collectionName}» →
                  </Link>
                )}
              </div>
              <div className="forms">
                {siblings.map((s) => (
                  <Link key={s.id} href={`/bouquet/${s.slug}`} className={s.id === b.id ? "on" : ""}>
                    <span className="ph"><img src={s.photos[0]} alt={FORMATS[s.format].inForm} /></span>
                    <span className="t stack" style={{ gap: 2 }}>
                      <b style={{ fontSize: 14 }}>{FORMATS[s.format].inForm}</b>
                      {s.id === b.id ? (
                        <span style={{ color: "var(--olive)", fontWeight: 700 }}>Вы смотрите</span>
                      ) : (
                        <span className="muted">{formatByn(s.priceKop)}</span>
                      )}
                    </span>
                  </Link>
                ))}
              </div>
            </div>
          )}

          <BuyBox
            bouquetId={b.id}
            sizes={b.sizes}
            defaultSize={b.defaultSize}
            addons={addons.map((a) => ({ id: a.id, name: a.name, priceKop: a.priceKop, included: a.included }))}
            pickupPct={delivery.pickupDiscountPct}
            viber={shop.viber}
          />

          <DeliveryEstimate subtotalKop={def.priceKop} districts={districts} freeFromKop={delivery.freeFromKop} leadHours={shop.leadHours} />

          <div className="acc">
            <details open>
              <summary>Состав</summary>
              <div className="stack" style={{ gap: 6 }}>
                {b.description && <p>{b.description}</p>}
                {b.sizes.map((s) => (
                  <p key={s.code}>
                    <b>{s.code}:</b> {s.composition}
                  </p>
                ))}
              </div>
            </details>
            <details>
              <summary>Как ухаживать</summary>
              <div>
                Подрежьте стебли наискось на 1–2 см, налейте прохладную воду и добавьте подкормку из пакетика. Меняйте воду через день и держите букет подальше от батареи и прямого солнца.
              </div>
            </details>
            <details>
              <summary>Доставка и оплата</summary>
              <div>
                Доставляем по Минску и району из ближайшего магазина: {delivery.shops.map((s) => s.name).join(" или ")}. Самовывоз со скидкой {delivery.pickupDiscountPct}%. Оплата картой на сайте, по ЕРИП или ссылкой после согласования фото.
              </div>
            </details>
          </div>
        </div>
      </div>

      <div className="perks" style={{ padding: 28 }}>
        <div><b style={{ fontSize: 20 }}>Фото букета перед отправкой</b><span className="muted">Пришлём в Viber или Telegram</span></div>
        <div><b style={{ fontSize: 20 }}>Открытка бесплатно</b><span className="muted">Напишем ваш текст от руки</span></div>
        <div><b style={{ fontSize: 20 }}>Сегодня до 21:00</b><span className="muted">Доставка рядом с магазином</span></div>
        <div><b style={{ fontSize: 20 }}>Самовывоз −{delivery.pickupDiscountPct}%</b><span className="muted">{delivery.shops.map((s) => s.name).join(" и ")}</span></div>
      </div>

      {similar.length > 0 && (
        <section className="stack" style={{ gap: 20 }}>
          <h2 className="h2">Похожие <span className="script">букеты</span></h2>
          <div className="grid">
            {similar.map((x) => <ProductCard key={x.id} b={x} />)}
          </div>
        </section>
      )}
    </div>
  );
}
