import type { Metadata } from "next";
import Link from "next/link";
import { ProductCard } from "@/components/ProductCard";
import { filterBouquets, visibleBouquets } from "@/lib/catalog";
import { db } from "@/lib/db";
import { minDeliveryKop } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { FORMATS, OCCASIONS, PRICE_RANGES } from "@/lib/occasions";
import { getSetting } from "@/lib/settings";

type SP = { occasion?: string; price?: string; flower?: string; format?: string; collection?: string; q?: string };

export async function generateMetadata({ searchParams }: { searchParams: Promise<SP> }): Promise<Metadata> {
  const sp = await searchParams;
  const occ = OCCASIONS.find((o) => o.slug === sp.occasion);
  return { title: occ ? `Букеты ${occ.title}` : "Каталог букетов" };
}

function href(sp: SP, patch: Partial<SP>): string {
  const next = { ...sp, ...patch };
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(next)) if (v) qs.set(k, v);
  const s = qs.toString();
  return s ? `/catalog?${s}` : "/catalog";
}

export default async function Catalog({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const [all, flowers, addons, shop, delivery] = await Promise.all([
    visibleBouquets(),
    db.flower.findMany({ where: { kind: "FLOWER" }, orderBy: [{ sortOrder: "asc" }, { name: "asc" }] }),
    db.addon.findMany({ where: { active: true }, orderBy: { sortOrder: "asc" } }),
    getSetting("shop"),
    getSetting("delivery"),
  ]);
  const range = PRICE_RANGES.find((r) => r.slug === sp.price);
  const list = filterBouquets(all, {
    occasion: sp.occasion,
    price: range ? { min: range.min, max: range.max } : undefined,
    flower: sp.flower,
    format: sp.format,
    collection: sp.collection,
    q: sp.q,
  });
  const occ = OCCASIONS.find((o) => o.slug === sp.occasion);
  const deliveryNote = `Доставка от ${formatByn(minDeliveryKop(delivery))}`;
  const activeFilters = [range?.label, sp.flower].filter(Boolean) as string[];

  return (
    <div className="wrap catalog">
      <aside className="filters">
        <div>
          <h3>Цена</h3>
          {PRICE_RANGES.map((r) => (
            <Link key={r.slug} href={href(sp, { price: sp.price === r.slug ? undefined : r.slug })} className={sp.price === r.slug ? "on" : ""}>
              {r.label}
            </Link>
          ))}
        </div>
        <div>
          <h3>Цветок</h3>
          {flowers.map((f) => (
            <Link key={f.id} href={href(sp, { flower: sp.flower === f.name ? undefined : f.name })} className={sp.flower === f.name ? "on" : ""}>
              {f.name}
            </Link>
          ))}
        </div>
        <div>
          <h3>Формат</h3>
          {Object.entries(FORMATS).map(([code, f]) => (
            <Link key={code} href={href(sp, { format: sp.format === code ? undefined : code })} className={sp.format === code ? "on" : ""}>
              {f.label}
            </Link>
          ))}
        </div>
      </aside>

      <div className="stack" style={{ gap: 20, minWidth: 0 }}>
        <div className="stack" style={{ gap: 8 }}>
          <h1 className="h2">
            {sp.collection ? (
              <>
                Коллекция <span className="script">«{list[0]?.collectionName ?? sp.collection}»</span>
              </>
            ) : sp.q ? (
              <>
                Поиск: <span className="script">{sp.q}</span>
              </>
            ) : (
              <>
                Букеты {occ ? <span className="script">{occ.title}</span> : null}
                {range && <span className="muted" style={{ fontSize: 24 }}> · {range.label}</span>}
              </>
            )}
          </h1>
          {shop.promoLine && <p className="muted">{shop.promoLine}</p>}
        </div>
        <div className="row" style={{ gap: 8, flexWrap: "nowrap", overflowX: "auto" }}>
          {OCCASIONS.filter((o) => ["mom", "love", "wedding", "nothing", "exotic"].includes(o.slug)).map((o) => (
            <Link key={o.slug} href={href(sp, { occasion: sp.occasion === o.slug ? undefined : o.slug })} className={`chip ${sp.occasion === o.slug ? "on" : ""}`}>
              {o.label}
            </Link>
          ))}
        </div>

        {list.length > 0 ? (
          <div className="grid">
            {list.map((b) => (
              <ProductCard key={b.id} b={b} note={deliveryNote} />
            ))}
          </div>
        ) : sp.q ? (
          <div className="empty panel">
            <h2 className="h2">По запросу «{sp.q}» ничего нет</h2>
            <p className="muted">Проверьте написание или посмотрите похожее. Флорист соберёт букет и на заказ.</p>
            <div className="row" style={{ justifyContent: "center" }}>
              <a href={shop.viber} className="btn">Написать флористу в Viber</a>
              <Link href="/catalog">Смотреть весь каталог →</Link>
            </div>
          </div>
        ) : (
          <div className="empty panel">
            <h2 className="h2">Под эти фильтры букетов нет</h2>
            {activeFilters.length > 0 && (
              <p className="muted">Попробуйте убрать один из фильтров: {activeFilters.map((f) => `«${f}»`).join(" или ")}.</p>
            )}
            <div className="row" style={{ justifyContent: "center" }}>
              <Link href="/catalog" className="btn">Сбросить фильтры</Link>
              {range && <Link href={href(sp, { price: undefined })}>Показать без фильтра по цене →</Link>}
            </div>
          </div>
        )}

        {addons.length > 0 && (
          <div className="stack" style={{ marginTop: 16 }}>
            <h2 style={{ fontSize: 32 }}>Добавить к букету</h2>
            <div className="row" style={{ gap: 10 }}>
              {addons.map((a) => (
                <div key={a.id} className="card" style={{ flex: "1 1 160px" }}>
                  <div style={{ fontWeight: 700 }}>{a.name}</div>
                  <div className="small" style={{ color: a.priceKop === 0 ? "var(--olive)" : "var(--muted)", fontWeight: 700 }}>
                    {a.priceKop === 0 ? "Бесплатно" : formatByn(a.priceKop)}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
