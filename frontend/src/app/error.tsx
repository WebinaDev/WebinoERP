"use client"

export default function Error({
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}) {
  return (
    <div className="mx-auto flex min-h-[50vh] max-w-lg flex-col items-center justify-center gap-4 px-4 py-16 text-center">
      <h1 className="text-xl font-semibold">صفحه در دسترس نیست</h1>
      <p className="text-muted-foreground text-sm">
        اگر تازه نصب کرده‌اید، چند ثانیه صبر کنید و دوباره تلاش کنید.
      </p>
      <div className="flex flex-wrap justify-center gap-3">
        <button
          type="button"
          className="rounded-md bg-[#0066FF] px-4 py-2 text-sm text-white"
          onClick={() => reset()}
        >
          تلاش مجدد
        </button>
        <a href="/login" className="rounded-md border px-4 py-2 text-sm">
          ورود به پنل
        </a>
      </div>
    </div>
  )
}
