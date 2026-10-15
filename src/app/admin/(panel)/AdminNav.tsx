"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const ITEMS = [
  { href: "/admin/bouquets", label: "Букеты" },
  { href: "/admin/flowers", label: "Цветы" },
  { href: "/admin/collections", label: "Коллекции" },
  { href: "/admin/orders", label: "Заказы" },
  { href: "/admin/settings", label: "Настройки" },
];

export function AdminNav() {
  const path = usePathname();
  return (
    <>
      {ITEMS.map((i) => (
        <Link key={i.href} href={i.href} className={`item ${path.startsWith(i.href) ? "on" : ""}`}>
          {i.label}
        </Link>
      ))}
    </>
  );
}
