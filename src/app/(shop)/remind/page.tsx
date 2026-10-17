import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { z } from "zod";
import { headers } from "next/headers";
import { db } from "@/lib/db";
import { allow, clientIp } from "@/lib/rateLimit";

export const metadata: Metadata = { title: "Напомним о важных датах" };

const schema = z.object({
  name: z.string().trim().min(1).max(100),
  phone: z.string().transform((s) => s.replace(/[^\d+]/g, "")).refine((s) => /^\+?\d{9,13}$/.test(s)),
  occasion: z.string().trim().min(1).max(100),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
});

async function save(formData: FormData) {
  "use server";
  if (!allow("remind", clientIp(await headers()))) redirect("/remind?error=busy");
  const parsed = schema.safeParse(Object.fromEntries(formData));
  if (!parsed.success) redirect("/remind?error=1");
  await db.reminder.create({ data: { ...parsed.data, date: new Date(parsed.data.date + "T00:00:00Z") } });
  redirect("/remind?ok=1");
}

export default async function Remind({ searchParams }: { searchParams: Promise<{ ok?: string; error?: string }> }) {
  const sp = await searchParams;
  return (
    <div className="section" style={{ maxWidth: 640 }}>
      <div className="panel stack" style={{ gap: 16 }}>
        <h1 className="h2">Напомним о <span className="script">важных датах</span></h1>
        {sp.ok ? (
          <p style={{ fontSize: 18 }}>Готово! За 5 дней до даты пришлём в Viber подборку букетов и скидку 5%.</p>
        ) : (
          <form action={save} className="stack" style={{ gap: 12 }}>
            <p className="muted">Оставьте дату, за 5 дней пришлём в Viber подборку букетов и скидку 5%.</p>
            <label className="field"><span>Ваше имя</span><input className="input" name="name" required /></label>
            <label className="field"><span>Телефон с Viber</span><input className="input" name="phone" type="tel" defaultValue="+375 " required /></label>
            <label className="field"><span>Повод</span><input className="input" name="occasion" placeholder="День рождения мамы" required /></label>
            <label className="field"><span>Дата</span><input className="input" name="date" type="date" required /></label>
            {sp.error && <p className="error">{sp.error === "busy" ? "Слишком много заявок подряд, попробуйте позже." : "Проверьте поля, что-то заполнено не так."}</p>}
            <button className="btn btn-lg" type="submit">Сохранить дату</button>
          </form>
        )}
      </div>
    </div>
  );
}
