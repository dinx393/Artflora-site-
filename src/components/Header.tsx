import Link from "next/link";
import { CartLink } from "./CartLink";
import { SearchIcon } from "./icons";
import type { ContactLink } from "@/lib/contacts";
import type { Shop } from "@/lib/delivery";
import { formatByn } from "@/lib/money";
import { MobileMenu } from "./MobileMenu";
import { PhoneMenu } from "./PhoneMenu";

export function Header({
  phone,
  contacts,
  instagram,
  shops,
  minDeliveryKop,
  freeFromKop,
  pickupPct,
}: {
  phone: string;
  contacts: ContactLink[];
  instagram: string;
  shops: Shop[];
  minDeliveryKop: number;
  freeFromKop: number;
  pickupPct: number;
}) {
  return (
    <>
      {/* Вишнёвая полоска — над логотипом (решение владельца 2026-10-19) */}
      <div className="ribbon">
        <div className="wrap">
          <b>Сегодня доставим до 21:00</b>
          <i aria-hidden>·</i>
          <span className="desktop-only">Доставка от {formatByn(minDeliveryKop)}, бесплатно от {formatByn(freeFromKop)}</span>
          <i aria-hidden className="desktop-only">·</i>
          <span className="desktop-only">Фото букета перед отправкой</span>
          <span className="mobile-only">Фото до отправки</span>
          <i aria-hidden className="desktop-only">·</i>
          <span className="desktop-only">Самовывоз −{pickupPct}%</span>
        </div>
      </div>
      <header className="header">
        <div className="wrap">
          <div className="header-top">
            <MobileMenu phone={phone} contacts={contacts} instagram={instagram} shops={shops} />
            <Link href="/" className="logo">
              Артфлора
            </Link>
            <form action="/catalog" className="search" role="search">
              <SearchIcon />
              <label className="visually-hidden" htmlFor="q">
                Поиск
              </label>
              <input id="q" name="q" type="search" placeholder="Розы, хризантемы, маме…" />
            </form>
            <div className="desktop-only">
              <PhoneMenu phone={phone} links={contacts} />
            </div>
            <CartLink />
          </div>
          <nav className="nav" aria-label="Разделы">
            <Link href="/catalog">Букеты</Link>
            <Link href="/#occasions">По поводу</Link>
            <Link href="/catalog?format=BASKET">Композиции</Link>
            <Link href="/catalog?occasion=wedding">Свадьба</Link>
            <Link href="/single">Поштучно</Link>
            <Link href="/delivery">Доставка и оплата</Link>
          </nav>
        </div>
      </header>
    </>
  );
}
