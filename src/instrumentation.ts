// Запускается один раз при старте сервера.
export async function register() {
  if (process.env.NEXT_RUNTIME !== "nodejs") return;
  const { configProblems } = await import("./lib/config");
  const problems = configProblems(process.env);
  if (problems.length) {
    console.error("Сайт не запущен, исправьте настройки в .env:\n- " + problems.join("\n- "));
    process.exit(1);
  }
  const { startNotifyRetries } = await import("./lib/notify");
  startNotifyRetries();
}
