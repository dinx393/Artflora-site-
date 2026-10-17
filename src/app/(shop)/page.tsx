import Link from "next/link";
import { ProductCard } from "@/components/ProductCard";
import { OccasionIcon } from "@/components/icons";
import { visibleBouquets } from "@/lib/catalog";
import { minDeliveryKop } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { OCCASIONS, PRICE_RANGES } from "@/lib/occasions";
import { getSetting } from "@/lib/settings";

export default async function Home({ searchParams }: { searchParams: Promise<{ price?: string }> }) {
  const { price } = await searchParams;
  const [all, shop, delivery] = await Promise.all([visibleBouquets(), getSetting("shop"), getSetting("delivery")]);
  const range = PRICE_RANGES.find((r) => r.slug === price);
  const hitsAll = all.filter((b) => b.isHit).length >= 4 ? all.filter((b) => b.isHit) : all;
  const hits = (range ? hitsAll.filter((b) => b.priceKop >= range.min && b.priceKop < range.max) : hitsAll).slice(0, 8);
  const heroPhoto = all.find((b) => b.isHit)?.photos[0] ?? all[0]?.photos[0] ?? "/demo/hero.jpg";

  return (
    <>
      <section className="hero">
        <div className="stack" style={{ flex: "1 1 min(420px, 100%)", minWidth: 0, gap: 20 }}>
          <p style={{ fontWeight: 700, fontSize: 15, letterSpacing: 1, textTransform: "uppercase" }}>
            Сеть салонов цветов: Уручье, Малиновка
          </p>
          <h1>
            Букетов много,<br /> а <span className="script">запомнят</span> ваш
          </h1>
          <p className="muted" style={{ fontSize: 19, lineHeight: 1.5 }}>
            Радуем букетами уже 19 лет.
          </p>
          <div className="row" style={{ gap: 12 }}>
            <Link href="/catalog" className="btn btn-lg">
              Выбрать букет
            </Link>
            <a href={shop.viber} className="btn btn-lg btn-outline">
              Написать в Viber
            </a>
          </div>
        </div>
        <div style={{ flex: "1 1 min(420px, 100%)", minWidth: 0, display: "flex", justifyContent: "center" }}>
          <div className="hero-photo">
            <img src={heroPhoto} alt="Букет от Артфлоры" />
          </div>
        </div>
      </section>

      <section className="section" id="occasions">
        <h2 className="h2" style={{ marginBottom: 24 }}>
          Кому <span className="script">дарим?</span>
        </h2>
        <div className="occasions">
          {OCCASIONS.filter((o) => o.slug !== "exotic").map((o) => (
            <Link key={o.slug} href={`/catalog?occasion=${o.slug}`} className="occasion">
              <span>
                <OccasionIcon slug={o.slug} />
              </span>
              {o.label}
            </Link>
          ))}
        </div>
      </section>

      <section className="section">
        <div className="row between" style={{ alignItems: "baseline", marginBottom: 20, gap: 12 }}>
          <h2 className="h2">
            Хиты <span className="script">этой недели</span>
          </h2>
          <div className="row" style={{ gap: 8 }}>
            {PRICE_RANGES.map((r) => (
              <Link
                key={r.slug}
                href={price === r.slug ? "/" : `/?price=${r.slug}#hits`}
                className={`chip ${price === r.slug ? "on" : ""}`}
                scroll={false}
              >
                {r.label}
              </Link>
            ))}
          </div>
        </div>
        <div className="grid" id="hits">
          {hits.map((b) => (
            <ProductCard key={b.id} b={b} />
          ))}
        </div>
        {hits.length === 0 && (
          <p className="muted">
            В этом диапазоне хитов нет. <Link href="/catalog">Смотреть весь каталог →</Link>
          </p>
        )}
      </section>

      <section className="section">
        <div className="perks">
          <div>
            <b>В день заказа</b>
            <span className="muted">Доставка из ближайшего магазина, от {formatByn(minDeliveryKop(delivery))}</span>
          </div>
          <div>
            <b>Фото</b>
            <span className="muted">Согласуем букет с вами до отправки</span>
          </div>
          <div>
            <b>В подарок</b>
            <span className="muted">Открытка и подкормка к каждому букету</span>
          </div>
          <div>
            <b>−{delivery.pickupDiscountPct}%</b>
            <span className="muted">При самовывозе в Уручье и Малиновке</span>
          </div>
        </div>
      </section>

      <section className="section row" style={{ gap: 24 }}>
        <div className="stack" style={{ flex: "1 1 360px", gap: 12 }}>
          <h2 className="h2">
            В каждом заказе <span className="script">в подарок</span>
          </h2>
          <p className="muted" style={{ fontSize: 18, lineHeight: 1.5 }}>
            Открытка с вашим текстом и 2 пакетика подкормки, чтобы букет стоял дольше.
          </p>
        </div>
        <div style={{ flex: "1 1 480px", display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <div className="panel" style={{ padding: 20, textAlign: "center", fontWeight: 700 }}>
            Открытка
          </div>
          <div className="panel" style={{ padding: 20, textAlign: "center", fontWeight: 700 }}>
            Подкормка
          </div>
        </div>
      </section>

      <section className="section" style={{ paddingTop: 8 }}>
        <div className="panel row between" style={{ gap: 20 }}>
          <div className="stack" style={{ gap: 6, flex: "1 1 360px" }}>
            <div style={{ fontFamily: "var(--serif)", fontSize: 32, fontWeight: 600 }}>Напомним о важных датах</div>
            <div className="muted">Оставьте дату, за 5 дней пришлём в Viber подборку букетов и скидку 5%.</div>
          </div>
          <Link href="/remind" className="btn btn-lg">
            Добавить дату
          </Link>
        </div>
      </section>
    </>
  );
}
