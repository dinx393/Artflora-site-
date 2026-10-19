"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";

// Фильтры каталога. На ПК — колонка слева, все группы раскрыты.
// На телефоне — одна кнопка «Фильтры»: по нажатию открывается список групп сверху вниз,
// каждая группа сворачивается. Выбор фильтра обновляет страницу, а панель остаётся открытой.
export function CatalogFilters({ active, found, children }: { active: number; found: number; children: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLElement>(null);

  useEffect(() => {
    const desktop = window.matchMedia("(min-width: 861px)");
    const sync = () => {
      if (desktop.matches) ref.current?.querySelectorAll("details").forEach((d) => (d.open = true));
    };
    sync();
    desktop.addEventListener("change", sync);
    return () => desktop.removeEventListener("change", sync);
  }, []);

  function close() {
    setOpen(false);
    // ждём, пока панель свернётся, иначе прокрутка промахнётся
    requestAnimationFrame(() => document.getElementById("catalog-list")?.scrollIntoView({ behavior: "smooth", block: "start" }));
  }

  return (
    <div className="filters-col">
      <div className="filters-toggle mobile-only">
        <button type="button" className={`btn ${open ? "" : "btn-outline"}`} aria-expanded={open} onClick={() => setOpen((o) => !o)}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
            <path d="M4 6h16M7 12h10M10 18h4" />
          </svg>
          Фильтры{active > 0 && <span className="filters-count">{active}</span>}
        </button>
        {active > 0 && (
          <Link href="/catalog" className="small" style={{ fontWeight: 700 }}>
            Сбросить
          </Link>
        )}
      </div>
      <aside ref={ref} className={`filters ${open ? "open" : ""}`}>
        {children}
        <button type="button" className="btn btn-lg filters-done mobile-only" onClick={close}>
          Показать {found} {plural(found)}
        </button>
      </aside>
    </div>
  );
}

function plural(n: number): string {
  const d = n % 10;
  const h = n % 100;
  if (d === 1 && h !== 11) return "букет";
  if (d >= 2 && d <= 4 && (h < 12 || h > 14)) return "букета";
  return "букетов";
}
