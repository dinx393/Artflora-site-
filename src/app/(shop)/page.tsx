import Link from "next/link";
import { ProductCard } from "@/components/ProductCard";
import { GUARANTEE_TITLE, GUARANTEE_TEXT } from "@/components/Guarantee";
import { CameraIcon, CardIcon, ClockIcon, ShieldIcon, OccasionIcon } from "@/components/icons";
import { visibleBouquets, type BouquetView } from "@/lib/catalog";
import { minDeliveryKop } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { OCCASIONS, PRICE_RANGES } from "@/lib/occasions";
import { getSetting } from "@/lib/settings";
import { Byn } from "@/components/Byn";

// Вырез в вишнёвом блоке главной: справа сверху встаёт «Букет дня», снизу — фото.
const HERO_NOTCH =
  "M706 336C706 349.26 695.26 360 682 360H553.82C540.57 360 529.82 370.75 529.82 384V564C529.82 577.26 519.08 588 505.82 588H24C10.75 588 0 577.26 0 564V24C0 10.75 10.75 0 24 0H682C695.26 0 706 10.75 706 24V336Z";

// «Огненный экзотический букет»: первое слово — рукописным, остальное — обычным шрифтом.
function splitName(name: string): [string, string] {
  const clean = name.replace(/[«»"]/g, "").trim();
  const [first, ...rest] = clean.split(/\s+/);
  if (!rest.length || !/^\p{L}+$/u.test(first)) return ["", clean];
  return [first, rest.join(" ")];
}

function ExclusiveCard({ b }: { b: BouquetView }) {
  const [script, text] = splitName(b.name);
  const size = b.sizes.find((s) => s.code === b.defaultSize);
  return (
    <Link href={`/bouquet/${b.slug}`} className="story">
      <img src={b.photos[0]} alt={b.name} loading="lazy" />
      <span className="story-shade" />
      <span className="story-title">
        {script && <span className="story-script">{script}</span>}
        <span className="story-text">{text}</span>
      </span>
      <span className="story-price"><Byn kop={b.priceKop} /></span>
      {size?.note && <span className="story-note">{size.note}</span>}
    </Link>
  );
}

export default async function Home({ searchParams }: { searchParams: Promise<{ price?: string }> }) {
  const { price } = await searchParams;
  const [all, shop, delivery] = await Promise.all([visibleBouquets(), getSetting("shop"), getSetting("delivery")]);
  const range = PRICE_RANGES.find((r) => r.slug === price);
  const hitsAll = all.filter((b) => b.isHit).length >= 4 ? all.filter((b) => b.isHit) : all;
  const hits = (range ? hitsAll.filter((b) => b.priceKop >= range.min && b.priceKop < range.max) : hitsAll).slice(0, 8);
  const ofDay = all.find((b) => b.isHit) ?? all[0];
  const tilePhoto = all.find((b) => b !== ofDay && b.photos[0] !== ofDay?.photos[0])?.photos[0] ?? "/demo/hero.jpg";
  const exclusive = all.filter((b) => b.isExclusive).slice(0, 3);
  const minDelivery = formatByn(minDeliveryKop(delivery));

  return (
    <>
      <section className="wrap hero-wrap">
        <div className="hero">
          <div className="hero-main">
            <svg className="hero-shape" viewBox="0 0 706 588" preserveAspectRatio="none" aria-hidden>
              <path d={HERO_NOTCH} />
            </svg>
            <div className="hero-text">
              <span className="eyebrow">Сеть салонов цветов: Уручье, Малиновка</span>
              <h1>
                Букетов много,
                <br /> а запомнят <span className="script">ваш</span>
              </h1>
              <p>Радуем букетами уже 19 лет. Соберём, пришлём фото до отправки и привезём в день заказа.</p>
            </div>
            <div className="hero-actions">
              <Link href="/catalog" className="btn btn-pink">
                Выбрать букет
              </Link>
              <a href={shop.viber} className="btn btn-on-cherry">
                Написать в Viber
              </a>
            </div>
          </div>
          {ofDay && (
            <Link href={`/bouquet/${ofDay.slug}`} className="hero-day">
              <img src={ofDay.photos[0]} alt={ofDay.name} />
              <span className="hero-day-body">
                <span className="eyebrow muted">Букет дня · собран сегодня</span>
                <span className="hero-day-row">
                  <span className="hero-day-name">{ofDay.name}</span>
                  <span className="price"><Byn kop={ofDay.priceKop} /></span>
                </span>
              </span>
            </Link>
          )}
          <Link href="/catalog" className="hero-tile">
            <img src={tilePhoto} alt="Букеты Артфлоры" />
            <span className="hero-chip">Весь каталог ↗</span>
          </Link>
        </div>
      </section>

      <section className="section" id="occasions">
        <h2 className="h2 sec-title">
          Кому сегодня <span className="script">дарим?</span>
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
        <div className="row between sec-title" style={{ alignItems: "flex-end", gap: 16 }}>
          <h2 className="h2">
            Хиты <span className="script">этой недели</span>
          </h2>
          <div className="row chips-row" style={{ gap: 8 }}>
            <Link href="/#hits" className={`chip ${!range ? "on" : ""}`} scroll={false}>
              Все
            </Link>
            {PRICE_RANGES.map((r) => (
              <Link
                key={r.slug}
                href={price === r.slug ? "/#hits" : `/?price=${r.slug}#hits`}
                className={`chip ${price === r.slug ? "on" : ""}`}
                scroll={false}
              >
                {r.label}
              </Link>
            ))}
          </div>
        </div>
        <div className="grid grid-4" id="hits">
          {hits.map((b) => (
            <ProductCard key={b.id} b={b} />
          ))}
        </div>
        {hits.length === 0 && (
          <p className="muted">
            В этом диапазоне хитов нет. <Link href="/catalog">Смотреть весь каталог →</Link>
          </p>
        )}
        <div className="more">
          <Link href="/catalog" className="btn btn-outline btn-lg">
            Смотреть весь каталог →
          </Link>
        </div>
      </section>

      <section className="section">
        <div className="band">
          <div className="stack" style={{ flex: "1 1 420px", gap: 12 }}>
            <h2>
              Не нашли свой? Соберём <span className="script">вместе.</span>
            </h2>
            <p>Напишите повод, любимые цвета и бюджет. Флорист предложит состав и пришлёт фото до отправки.</p>
          </div>
          <a href={shop.viber} className="btn btn-pink btn-lg">
            Написать флористу
          </a>
        </div>
      </section>

      <section className="section">
        <h2 className="h2 sec-title">
          Важно <span className="script">знать</span>
        </h2>
        <div className="cards-4">
          <div className="info-card info-accent">
            <i><ShieldIcon /></i>
            <h3>{GUARANTEE_TITLE.replace("Гарантия свежести", "Гарантия")}</h3>
            <p>{GUARANTEE_TEXT}</p>
          </div>
          <div className="info-card">
            <i><ClockIcon /></i>
            <h3>В день заказа</h3>
            <p>Везём из ближайшего магазина, доставка от {minDelivery}. Самовывоз −{delivery.pickupDiscountPct}%.</p>
          </div>
          <div className="info-card">
            <i><CameraIcon /></i>
            <h3>Фото до отправки</h3>
            <p>Согласуем букет с вами, прежде чем курьер выедет.</p>
          </div>
          <div className="info-card">
            <i><CardIcon /></i>
            <h3>Открытка и подкормка</h3>
            <p>
              Кладём <span className="gift">в подарок</span> к каждому букету.
            </p>
          </div>

        </div>
      </section>

      <section className="section">
        <div className="panel row between" style={{ gap: 20 }}>
          <div className="stack" style={{ gap: 8, flex: "1 1 360px" }}>
            <h2 style={{ fontSize: 32 }}>Напомним о важных датах</h2>
            <p className="muted">Оставьте дату, и за 5 дней пришлём в Viber подборку букетов и скидку 5%.</p>
          </div>
          <Link href="/remind" className="btn btn-lg">
            Добавить дату
          </Link>
        </div>
      </section>

      {exclusive.length > 0 && (
        <section className="section">
          <div className="stack sec-title" style={{ gap: 10 }}>
            <span className="eyebrow muted">Только на этой неделе</span>
            <h2 className="h2">
              Эксклюзив <span className="script">этой недели</span>
            </h2>
          </div>
          <div className="stories">
            {exclusive.map((b) => (
              <ExclusiveCard key={b.id} b={b} />
            ))}
          </div>
        </section>
      )}
    </>
  );
}
