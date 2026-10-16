import type { ReactNode } from "react"

import { cn } from "@/lib/utils"

export function PageShell({
  title: _title,
  description,
  eyebrow,
  actions,
  children,
  className,
}: {
  title: string
  description?: string
  eyebrow?: string
  actions?: ReactNode
  children?: ReactNode
  className?: string
}) {
  // Title is shown in the dashboard chrome breadcrumb/path — do not repeat as page H1.
  const showIntro = Boolean(eyebrow || description || actions)
  return (
    <div className={cn("wd-page-shell min-w-0 max-w-full space-y-4 overflow-x-hidden text-start sm:space-y-5", className)}>
      {showIntro ? (
        <header className="flex min-w-0 flex-wrap items-start justify-between gap-3 sm:gap-4">
          <div className="min-w-0 space-y-1.5">
            {eyebrow ? (
              <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{eyebrow}</p>
            ) : null}
            {description ? (
              <p className="text-muted-foreground max-w-2xl text-sm font-normal leading-relaxed">{description}</p>
            ) : null}
          </div>
          {actions ? <div className="flex min-w-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
      ) : null}
      {children}
    </div>
  )
}
