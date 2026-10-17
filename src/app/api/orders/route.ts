import { NextResponse } from "next/server";
import { createOrder } from "@/lib/checkout";
import { allow, clientIp } from "@/lib/rateLimit";

export async function POST(req: Request) {
  if (!allow("order", clientIp(req.headers))) {
    return NextResponse.json({ ok: false, error: "Слишком много заказов подряд. Позвоните нам, пожалуйста" }, { status: 429 });
  }
  const raw = await req.json().catch(() => null);
  const result = await createOrder(raw);
  return NextResponse.json(result, { status: result.ok ? 201 : 422 });
}
