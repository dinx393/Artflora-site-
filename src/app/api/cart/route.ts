import { NextResponse } from "next/server";
import { z } from "zod";
import { computeTotals } from "@/lib/checkout";
import { SIZE_ORDER } from "@/lib/pricing";

// Корзина по актуальным ценам: браузер присылает только id и размеры.
const body = z.object({
  cart: z.object({
    lines: z.array(z.object({ bouquetId: z.number().int(), size: z.enum(SIZE_ORDER), qty: z.number().int().min(1).max(20) })).max(30),
    addons: z.array(z.object({ addonId: z.number().int(), qty: z.number().int().min(1).max(10) })).max(20),
    cardText: z.string().max(500),
  }),
  method: z.enum(["DELIVERY", "PICKUP"]).default("DELIVERY"),
  point: z.object({ lat: z.number(), lng: z.number() }).nullable().default(null),
});

export async function POST(req: Request) {
  const parsed = body.safeParse(await req.json().catch(() => null));
  if (!parsed.success) return NextResponse.json({ error: "bad-request" }, { status: 400 });
  return NextResponse.json(await computeTotals(parsed.data));
}
