"use client"

import { RahnPublicEstimate } from "@/features/modules/sales/rahn_percent/rahn-public-estimate"

import { propStr } from "../props"
import type { WidgetNode } from "../types"

/** Embeds the public rahn estimate. Optional share `token`; without one it uses the token-free /pricing calculator. */
export function RahnEstimateWidget({ widget, editing }: { widget: WidgetNode; editing?: boolean }) {
  const token = propStr(widget.props, "token").replace(/[^a-zA-Z0-9]/g, "")
  const title = propStr(widget.props, "title")
  if (editing) {
    return (
      <p className="wbx-empty">
        {token
          ? `برآورد قیمت (توکن ${token.slice(0, 6)}…). فرم زنده روی سایت نمایش داده می‌شود.`
          : "برآورد قیمت عمومی (بدون توکن). فرم چندمرحله‌ای رهن‌درصد روی سایت نمایش داده می‌شود."}
      </p>
    )
  }
  return (
    <section className="wbx-rahn" aria-label={title || "برآورد قیمت"}>
      {title ? <h2 className="wbx-heading-title mb-4 text-center">{title}</h2> : null}
      <RahnPublicEstimate token={token || undefined} embedded />
    </section>
  )
}
