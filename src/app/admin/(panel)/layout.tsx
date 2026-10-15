import type { Metadata } from "next";
import Link from "next/link";
import { requireAdmin } from "@/lib/auth";
import { logout } from "../actions";
import { AdminNav } from "./AdminNav";

export const metadata: Metadata = { title: "Управление сайтом", robots: { index: false } };
export const dynamic = "force-dynamic";

export default async function PanelLayout({ children }: { children: React.ReactNode }) {
  await requireAdmin();
  return (
    <div className="admin">
      <aside className="admin-nav">
        <Link href="/admin/bouquets" className="logo">Артфлора</Link>
        <span className="small muted" style={{ padding: "0 0 14px" }}>Управление сайтом</span>
        <AdminNav />
        <Link href="/" className="item small" style={{ marginTop: 16, padding: "10px 12px", fontWeight: 700 }} target="_blank">
          Открыть сайт →
        </Link>
        <form action={logout}>
          <button className="btn-ghost small muted">Выйти</button>
        </form>
      </aside>
      <main className="admin-main">{children}</main>
    </div>
  );
}
