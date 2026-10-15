import type { Metadata } from "next";
import { LoginForm } from "./LoginForm";

export const metadata: Metadata = { title: "Вход в управление сайтом", robots: { index: false } };

export default function Login() {
  return (
    <div style={{ minHeight: "100vh", display: "flex", alignItems: "center", justifyContent: "center", padding: 24, background: "var(--blush)" }}>
      <div className="card stack" style={{ background: "var(--white)", width: 360, gap: 16, padding: 28 }}>
        <div className="logo">Артфлора</div>
        <div className="muted">Управление сайтом</div>
        <LoginForm />
      </div>
    </div>
  );
}
