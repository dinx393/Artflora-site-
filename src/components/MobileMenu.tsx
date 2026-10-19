"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { ContactLink } from "@/lib/contacts";
import type { Shop } from "@/lib/delivery";
import { OCCASIONS } from "@/lib/occasions";
import { CartLink } from "./CartLink";
import { CloseIcon, MenuIcon, SearchIcon } from "./icons";

const MENU_OCCASIONS = OCCASIONS.filter((o) => ["mom", "love", "birthday", "colleague", "nothing"].includes(o.slug));

// Меню на телефоне: кнопка-«бургер» в шапке открывает экран со всеми разделами и контактами.
export function MobileMenu({ phone, contacts, instagram, shops }: { phone: string; contacts: ContactLink[]; instagram: string; shops: Shop[] }) {
  const [open, setOpen] = useState(false);
  useEffect(() => {
    if (!open) return;
    const esc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", esc);
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", esc);
      document.body.style.overflow = "";
    };
  }, [open]);

  const call = contacts.find((c) => c.code === "call");
  const msg = contacts.filter((c) => c.code === "viber" || c.code === "telegram");

  return (
    <>
      <button type="button" className="icon-btn mobile-only" aria-label="Меню" aria-expanded={open} onClick={() => setOpen(true)}>
        <MenuIcon />
      </button>
      {open && (
        <div className="mmenu" role="dialog" aria-modal="true" aria-label="Меню">
          <div className="mmenu-top">
            <button type="button" className="icon-btn" aria-label="Закрыть меню" onClick={() => setOpen(false)}>
              <CloseIcon />
            </button>
            <Link href="/" className="logo" onClick={() => setOpen(false)}>
              Артфлора
            </Link>
            <CartLink />
          </div>
          <div className="mmenu-body">
            <form action="/catalog" className="search search-soft" role="search" onSubmit={() => setOpen(false)}>
              <SearchIcon />
              <label className="visually-hidden" htmlFor="mq">
                Поиск
              </label>
              <input id="mq" name="q" type="search" placeholder="Розы, хризантемы, маме…" />
            </form>
            <nav className="mmenu-nav" aria-label="Разделы">
              <Link href="/catalog" onClick={() => setOpen(false)}>Букеты<span aria-hidden>→</span></Link>
              <div className="mmenu-group">
                <span>По поводу</span>
                <div className="row" style={{ gap: 8 }}>
                  {MENU_OCCASIONS.map((o) => (
                    <Link key={o.slug} href={`/catalog?occasion=${o.slug}`} className="tag" onClick={() => setOpen(false)}>
                      {o.label}
                    </Link>
                  ))}
                </div>
              </div>
              <Link href="/catalog?format=BASKET" onClick={() => setOpen(false)}>Композиции<span aria-hidden>→</span></Link>
              <Link href="/catalog?occasion=wedding" onClick={() => setOpen(false)}>Свадьба<span aria-hidden>→</span></Link>
              <Link href="/single" onClick={() => setOpen(false)}>Поштучно<span aria-hidden>→</span></Link>
              <Link href="/delivery" className="plain" onClick={() => setOpen(false)}>Доставка и оплата<span aria-hidden>→</span></Link>
            </nav>
            <div className="mmenu-contact">
              <span className="eyebrow">Напишите или позвоните</span>
              {call && <a href={call.href} className="mmenu-phone">{phone}</a>}
              <div className="mmenu-msg">
                {msg.map((c) => (
                  <a key={c.code} href={c.href} target={c.href.startsWith("https://") ? "_blank" : undefined} rel="noreferrer">
                    {c.label}
                  </a>
                ))}
                <a href={instagram} target="_blank" rel="noreferrer">Instagram</a>
              </div>
            </div>
            <div className="stack small" style={{ gap: 12 }}>
              {shops.map((s) => (
                <div key={s.code} className="stack" style={{ gap: 2 }}>
                  <b>{s.name} · {s.address}</b>
                  <span className="muted">Ежедневно {s.hours}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
