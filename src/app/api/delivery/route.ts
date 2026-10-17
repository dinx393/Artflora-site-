import { NextResponse } from "next/server";
import { z } from "zod";
import { quoteDelivery } from "@/lib/delivery";
import { geocode } from "@/lib/geocode";
import { allow, clientIp } from "@/lib/rateLimit";
import { getSetting } from "@/lib/settings";

// Расчёт доставки для подсказки на сайте. Окончательную цену считает сервер при оформлении.
const body = z.object({
  address: z.string().max(300).optional(),
  point: z.object({ lat: z.number(), lng: z.number() }).optional(),
  subtotalKop: z.number().int().min(0).max(10_000_000).default(0),
});

export async function POST(req: Request) {
  const parsed = body.safeParse(await req.json().catch(() => null));
  if (!parsed.success) return NextResponse.json({ ok: false, reason: "bad-request" }, { status: 400 });
  const { address, subtotalKop } = parsed.data;
  if (!parsed.data.point && address && !allow("geocode", clientIp(req.headers))) {
    return NextResponse.json({ ok: false, reason: "busy" }, { status: 429 });
  }
  const point = parsed.data.point ?? (address ? await geocode(address) : null);
  if (!point) return NextResponse.json({ ok: false, reason: "not-found" });
  const cfg = await getSetting("delivery");
  return NextResponse.json({ ...quoteDelivery(point, subtotalKop, cfg), point });
}
