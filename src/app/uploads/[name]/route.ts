import { readFile } from "node:fs/promises";
import path from "node:path";
import { safeName, UPLOAD_DIR } from "@/lib/uploads";

const TYPES: Record<string, string> = { ".jpg": "image/jpeg", ".png": "image/png", ".webp": "image/webp" };

export async function GET(_req: Request, { params }: { params: Promise<{ name: string }> }) {
  const name = safeName((await params).name);
  if (!name) return new Response("Not found", { status: 404 });
  try {
    const data = await readFile(path.join(UPLOAD_DIR, name));
    return new Response(new Uint8Array(data), {
      headers: { "Content-Type": TYPES[path.extname(name)], "Cache-Control": "public, max-age=31536000, immutable" },
    });
  } catch {
    return new Response("Not found", { status: 404 });
  }
}
