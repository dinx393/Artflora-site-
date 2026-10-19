import type { Metadata, Viewport } from "next";
// Шрифты лежат в пакетах npm, а не качаются из Google при сборке:
// иначе сборка падает, если Google Fonts недоступен.
import "@fontsource-variable/manrope";
import "@fontsource-variable/playfair-display/wght.css";
import "@fontsource-variable/playfair-display/wght-italic.css";
import "@fontsource/great-vibes/400.css";
import "./globals.css";

export const metadata: Metadata = {
  metadataBase: new URL(process.env.SITE_URL ?? "https://artflora.by"),
  title: { default: "Артфлора — цветы и букеты с доставкой по Минску", template: "%s — Артфлора" },
  description:
    "Сеть салонов цветов в Уручье и Малиновке, радуем букетами уже 19 лет. Пришлём фото букета до отправки, доставим по Минску в день заказа. Самовывоз −10%.",
};

export const viewport: Viewport = { width: "device-width", initialScale: 1, themeColor: "#7E1F3C" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru">
      <body>{children}</body>
    </html>
  );
}
