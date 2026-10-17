"use client";

import { useEffect, useRef, useState } from "react";
import type { ContactLink } from "@/lib/contacts";
import { PhoneIcon } from "./icons";

// Номер в шапке: по нажатию выпадает выбор — Telegram, WhatsApp, Viber или звонок.
export function PhoneMenu({ phone, links }: { phone: string; links: ContactLink[] }) {
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const close = (e: MouseEvent | KeyboardEvent) => {
      if (e instanceof KeyboardEvent ? e.key === "Escape" : !box.current?.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", close);
    document.addEventListener("keydown", close);
    return () => {
      document.removeEventListener("mousedown", close);
      document.removeEventListener("keydown", close);
    };
  }, [open]);

  return (
    <div className="phone-menu" ref={box}>
      <button type="button" className="phone" aria-expanded={open} aria-haspopup="menu" onClick={() => setOpen((v) => !v)}>
        <span className="phone-icon"><PhoneIcon /></span>
        <span className="phone-number">{phone}</span>
      </button>
      {open && (
        <div className="phone-pop" role="menu">
          <span className="small muted">Напишите или позвоните нам</span>
          {links.map((l) => (
            <a
              key={l.code}
              role="menuitem"
              href={l.href}
              className={`phone-opt ${l.code}`}
              target={l.href.startsWith("https://") ? "_blank" : undefined}
              rel="noopener"
              onClick={() => setOpen(false)}
            >
              <span className="dot" aria-hidden>{l.code === "call" ? <PhoneIcon size={16} /> : l.label[0]}</span>
              {l.label}
            </a>
          ))}
        </div>
      )}
    </div>
  );
}
