"use client"

export default function GlobalError({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  return (
    <html lang="fa" dir="rtl">
      <body className="min-h-svh bg-background p-8 text-center font-sans text-foreground">
        <h1 className="text-xl font-semibold">خطای سرور</h1>
        <p className="mt-2 text-sm opacity-70">بارگذاری صفحه با خطا مواجه شد.</p>
        <div className="mt-6 flex justify-center gap-3">
          <button type="button" onClick={() => reset()}>
            تلاش مجدد
          </button>
          <a href="/login">ورود</a>
        </div>
      </body>
    </html>
  )
}
