import { NextResponse } from "next/server";
import { createOrder } from "@/lib/checkout";

export async function POST(req: Request) {
  const raw = await req.json().catch(() => null);
  const result = await createOrder(raw);
  return NextResponse.json(result, { status: result.ok ? 201 : 422 });
}
