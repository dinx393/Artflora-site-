import { NextResponse } from "next/server";
import { z } from "zod";
import { quoteDelivery } from "@/lib/delivery";
import { resolveAddress } from "@/lib/geocode";
import { allow, clientIp } from "@/lib/rateLimit";
import { getSetting } from "@/lib/settings";

// Расчёт доставки для подсказки на сайте. Окончательную цену считает сервер при оформлении,
// и только по адресу (точку из браузера при заказе не принимаем).
const body = z.object({
  address: z.string().max(300).optional(),
  placeId: z.number().int().positive().optional(),
  point: z.object({ lat: z.number(), lng: z.number() }).optional(),
  subtotalKop: z.number().int().min(0).max(10_000_000).default(0),
});

export async function POST(req: Request) {
  const parsed = body.safeParse(await req.json().catch(() => null));
  if (!parsed.success) return NextResponse.json({ ok: false, reason: "bad-request" }, { status: 400 });
  const { address, placeId, subtotalKop } = parsed.data;
  const cfg = await getSetting("delivery");

  if (address) {
    if (!allow("geocode", clientIp(req.headers))) {
      return NextResponse.json({ ok: false, reason: "busy" }, { status: 429 });
    }
    const r = await resolveAddress(address, placeId);
    if (!r.ok) {
      if (r.reason !== "choose") return NextResponse.json({ ok: false, reason: r.reason });
      // Таких адресов несколько: покупатель выбирает свой, к каждому сразу показываем цену.
      const choices = r.choices.map((c) => ({ ...c, quote: quoteDelivery(c.point, subtotalKop, cfg) }));
      return NextResponse.json({ ok: false, reason: "choose", choices });
    }
    const c = r.choice;
    return NextResponse.json({ ...quoteDelivery(c.point, subtotalKop, cfg), point: c.point, label: c.label, area: c.area, placeId: c.placeId });
  }

  const point = parsed.data.point;
  if (!point) return NextResponse.json({ ok: false, reason: "not-found" });
  return NextResponse.json({ ...quoteDelivery(point, subtotalKop, cfg), point });
}
