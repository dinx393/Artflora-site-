import type { Metadata } from "next";
import Link from "next/link";
import { ProductCard } from "@/components/ProductCard";
import { SortSelect } from "@/components/SortSelect";
import { filterBouquets, visibleBouquets } from "@/lib/catalog";
import { db } from "@/lib/db";
import { minDeliveryKop } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { FORMATS, OCCASIONS, PRICE_RANGES, parseColors, toggleColor } from "@/lib/occasions";
import { getSetting } from "@/lib/settings";
import { Byn } from "@/components/Byn";
import { CatalogFilters } from "@/components/CatalogFilters";

type SP = {
  occasion?: string;
  price?: string;
  flower?: string;
  color?: string;
  format?: string;
  collection?: string;
  q?: string;
  sort?: string;
  n?: string;
};

// Сколько букетов показывать сразу и добавлять по кнопке «Показать ещё».
const PAGE = 24;

export async function generateMetadata({ searchParams }: { searchParams: Promise<SP> }): Promise<Metadata> {
  const sp = await searchParams;
  const occ = OCCASIONS.find((o) => o.slug === sp.occasion);
  return { title: occ ? `Букеты ${occ.title}` : "Каталог букетов" };
}

// Быстрые разделы над каталогом.
const SECTIONS: { label: string; patch: Partial<SP>; href?: string }[] = [
  { label: "Розы", patch: { flower: "Роза" } },
  { label: "Экзотика", patch: { occasion: "exotic" } },
  { label: "Шляпные коробки", patch: { format: "HATBOX" } },
  { label: "Сумки с цветами", patch: { format: "BAG" } },
  { label: "Композиции", patch: { format: "BASKET" } },
  { label: "Поштучно", patch: {}, href: "/single" },
];

function isOn(sp: SP, patch: Partial<SP>): boolean {
  const keys = Object.keys(patch) as (keyof SP)[];
  return keys.length > 0 && keys.every((k) => sp[k] === patch[k]);
}

function href(sp: SP, patch: Partial<SP>): string {
  // При смене фильтра список снова начинается с первых PAGE букетов.
  const next = { ...sp, n: undefined, ...patch };
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(next)) if (v) qs.set(k, v);
  const s = qs.toString();
  return s ? `/catalog?${s}` : "/catalog";
}

export default async function Catalog({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const [all, flowers, addons, shop, delivery, colorList] = await Promise.all([
    visibleBouquets(),
    db.flower.findMany({ where: { kind: "FLOWER" }, orderBy: [{ sortOrder: "asc" }, { name: "asc" }] }),
    db.addon.findMany({ where: { active: true }, orderBy: { sortOrder: "asc" } }),
    getSetting("shop"),
    getSetting("delivery"),
    getSetting("colors"),
  ]);
  const range = PRICE_RANGES.find((r) => r.slug === sp.price);
  const colors = parseColors(sp.color, colorList);
  // В фильтре только цвета, которые есть хотя бы у одного букета (и уже выбранные).
  const palette = colorList.filter((c) => colors.includes(c.slug) || all.some((b) => b.colors.includes(c.slug)));
  const list = filterBouquets(all, {
    occasion: sp.occasion,
    price: range ? { min: range.min, max: range.max } : undefined,
    flower: sp.flower,
    colors,
    format: sp.format,
    collection: sp.collection,
    q: sp.q,
  });
  if (sp.sort === "cheap") list.sort((a, b) => a.priceKop - b.priceKop);
  if (sp.sort === "expensive") list.sort((a, b) => b.priceKop - a.priceKop);
  const shown = Math.max(PAGE, Math.floor(Number(sp.n) || 0));
  const occ = OCCASIONS.find((o) => o.slug === sp.occasion);
  const deliveryNote = `Доставка от ${formatByn(minDeliveryKop(delivery))}`;
  const colorLabels = colorList.filter((c) => colors.includes(c.slug)).map((c) => c.label.toLowerCase());
  const activeFilters = [range?.label, sp.flower, colorLabels.length ? `цвет: ${colorLabels.join(", ")}` : undefined].filter(Boolean) as string[];
  const allBouquets = !sp.occasion && !sp.flower && !sp.format && !sp.collection && !sp.q;
  const format = sp.format ? FORMATS[sp.format as keyof typeof FORMATS] : undefined;
  const activeCount = [range, occ, sp.flower, format].filter(Boolean).length + colors.length;
  const sortHrefs = { "": href(sp, { sort: undefined }), cheap: href(sp, { sort: "cheap" }), expensive: href(sp, { sort: "expensive" }) };

  return (
    <>
      <section className="wrap catalog-head">
        <nav className="crumbs" aria-label="Навигация">
          <Link href="/">Главная</Link>
          <span aria-hidden>/</span>
          <span>Каталог</span>
        </nav>
        <div className="row between" style={{ alignItems: "flex-end", gap: 16 }}>
          <h1 className="h1">
            {sp.collection ? (
              <>
                Коллекция <span className="script">«{list[0]?.collectionName ?? sp.collection}»</span>
              </>
            ) : sp.q ? (
              <>
                Поиск: <span className="script">{sp.q}</span>
              </>
            ) : occ ? (
              <>
                Букеты <span className="script">{occ.title}</span>
              </>
            ) : (
              "Каталог"
            )}
          </h1>
          <span className="muted">{shop.promoLine || "Перед отправкой пришлём фото именно вашего букета"}</span>
        </div>
        <div className="row chips-row" style={{ gap: 8 }}>
          <Link href="/catalog" className={`chip ${allBouquets ? "on" : ""}`}>
            Все букеты
          </Link>
          {SECTIONS.map((c) =>
            c.href ? (
              <Link key={c.label} href={c.href} className="chip">
                {c.label}
              </Link>
            ) : (
              <Link key={c.label} href={isOn(sp, c.patch) ? "/catalog" : href({}, c.patch)} className={`chip ${isOn(sp, c.patch) ? "on" : ""}`}>
                {c.label}
              </Link>
            ),
          )}
        </div>
      </section>

      <div className="wrap catalog">
        <CatalogFilters active={activeCount} found={list.length}>
          <details className="fgroup" open={!!range}>
            <summary>
              <h3>Цена</h3>
              {range && <span className="fgroup-val">{range.label}</span>}
            </summary>
            {PRICE_RANGES.map((r) => (
              <Link key={r.slug} href={href(sp, { price: sp.price === r.slug ? undefined : r.slug })} scroll={false} className={`radio ${sp.price === r.slug ? "on" : ""}`}>
                {r.label}
              </Link>
            ))}
          </details>
          <details className="fgroup" open={!!occ}>
            <summary>
              <h3>Кому</h3>
              {occ && <span className="fgroup-val">{occ.label}</span>}
            </summary>
            <div className="chips-wrap">
              {OCCASIONS.filter((o) => ["mom", "love", "colleague", "birthday", "nothing"].includes(o.slug)).map((o) => (
                <Link key={o.slug} href={href(sp, { occasion: sp.occasion === o.slug ? undefined : o.slug })} scroll={false} className={`chip chip-sm ${sp.occasion === o.slug ? "on" : ""}`}>
                  {o.label}
                </Link>
              ))}
            </div>
          </details>
          {palette.length > 0 && (
            <details className="fgroup" open={colors.length > 0}>
              <summary>
                <h3>Цвет</h3>
                {colorLabels.length > 0 && <span className="fgroup-val">{colorLabels.join(", ")}</span>}
              </summary>
              <p className="small muted" style={{ marginTop: -6, marginBottom: 10 }}>Можно выбрать несколько</p>
              <div className="chips-wrap">
                {palette.map((c) => (
                  <Link
                    key={c.slug}
                    href={href(sp, { color: toggleColor(colors, c.slug, colorList) })}
                    scroll={false}
                    className={`swatch-btn ${colors.includes(c.slug) ? "on" : ""}`}
                    title={c.label}
                    aria-label={colors.includes(c.slug) ? `${c.label}, выбран` : c.label}
                  >
                    <i className="swatch" style={{ background: c.swatch }} />
                  </Link>
                ))}
              </div>
              {colorLabels.length > 0 && (
                <p className="small" style={{ marginTop: 10 }}>
                  {colorLabels.join(", ")} ·{" "}
                  <Link href={href(sp, { color: undefined })} scroll={false} style={{ fontWeight: 700 }}>
                    сбросить
                  </Link>
                </p>
              )}
            </details>
          )}
          <details className="fgroup" open={!!sp.flower}>
            <summary>
              <h3>Цветок</h3>
              {sp.flower && <span className="fgroup-val">{sp.flower}</span>}
            </summary>
            <div className="chips-wrap">
              {flowers.map((f) => (
                <Link key={f.id} href={href(sp, { flower: sp.flower === f.name ? undefined : f.name })} scroll={false} className={`chip chip-sm ${sp.flower === f.name ? "on" : ""}`}>
                  {f.name}
                </Link>
              ))}
            </div>
          </details>
          <details className="fgroup" open={!!format}>
            <summary>
              <h3>Формат</h3>
              {format && <span className="fgroup-val">{format.label}</span>}
            </summary>
            {Object.entries(FORMATS).map(([code, f]) => (
              <Link key={code} href={href(sp, { format: sp.format === code ? undefined : code })} scroll={false} className={`box ${sp.format === code ? "on" : ""}`}>
                {f.label}
              </Link>
            ))}
          </details>
          <div className="help">
            <b>Нужна подсказка?</b>
            <span className="small muted">Флорист поможет выбрать в Viber.</span>
            <a href={shop.viber} className="small" style={{ fontWeight: 700 }}>
              Написать флористу →
            </a>
          </div>
        </CatalogFilters>

      <div id="catalog-list" className="stack" style={{ gap: 28, minWidth: 0 }}>
        <div className="catalog-bar">
          <span>
            Сегодня по Минску из ближайшего магазина. Доставка от {formatByn(minDeliveryKop(delivery))}, от {formatByn(delivery.freeFromKop)}{" "}
            <span className="gift">бесплатно</span>.
          </span>
          <SortSelect value={sp.sort === "cheap" || sp.sort === "expensive" ? sp.sort : ""} hrefs={sortHrefs} />
        </div>

        {list.length > 0 ? (
          <>
            <div className="grid grid-3">
              {list.slice(0, shown).map((b) => (
                <ProductCard key={b.id} b={b} note={deliveryNote} />
              ))}
            </div>
            {list.length > shown && (
              <div className="more">
                <Link href={href(sp, { n: String(shown + PAGE) })} scroll={false} className="btn btn-outline btn-lg">
                  Показать ещё
                </Link>
                <span className="small muted">
                  Показано {shown} из {list.length}
                </span>
              </div>
            )}
          </>
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
          <div className="addons">
            <h2>Добавить к букету</h2>
            <div className="addons-grid">
              {addons.map((a) => (
                <div key={a.id} className="addon">
                  <b>{a.name}</b>
                  {a.priceKop === 0 ? <span className="gift">В подарок</span> : <span className="price" style={{ fontSize: 18 }}><Byn kop={a.priceKop} /></span>}
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="band">
          <div className="stack" style={{ flex: "1 1 320px", gap: 10 }}>
            <h2>
              Тот самый букет ещё <span className="script">не придуман?</span>
            </h2>
            <p>Расскажите флористу, кому и по какому поводу. Соберём и пришлём фото до отправки.</p>
          </div>
          <a href={shop.viber} className="btn btn-pink btn-lg">
            Написать флористу
          </a>
        </div>
      </div>
      </div>
    </>
  );
}
