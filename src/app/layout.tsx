import type { Metadata, Viewport } from "next";
import { Cormorant_Garamond, Manrope, Marck_Script } from "next/font/google";
import "./globals.css";

const manrope = Manrope({ subsets: ["latin", "cyrillic"], variable: "--font-manrope", weight: ["400", "500", "600", "700", "800"] });
const cormorant = Cormorant_Garamond({ subsets: ["latin", "cyrillic"], variable: "--font-cormorant", weight: ["500", "600", "700"] });
const marck = Marck_Script({ subsets: ["latin", "cyrillic"], variable: "--font-marck", weight: "400" });

export const metadata: Metadata = {
  metadataBase: new URL(process.env.SITE_URL ?? "https://artflora.by"),
  title: { default: "Артфлора — цветы и букеты с доставкой по Минску", template: "%s — Артфлора" },
  description:
    "Цветочный магазин в Уручье и Малиновке. Пришлём фото букета до отправки, доставим по Минску в день заказа. Самовывоз −10%.",
};

export const viewport: Viewport = { width: "device-width", initialScale: 1, themeColor: "#7E1F3C" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru" className={`${manrope.variable} ${cormorant.variable} ${marck.variable}`}>
      <body>{children}</body>
    </html>
  );
}
