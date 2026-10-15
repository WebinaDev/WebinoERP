/* Widget definitions shared with WebinoDashboard, plus ERP form wiring.
 * CONTENT_WIDGETS: landing-page widgets (section heading, steps, icon cards, badges, CTA, comparison, rahn). */

import { uid } from "./ids"
import { sharedContentWidgets, sharedFormWidget } from "@webina/ui/builder"
import { ICON_OPTIONS } from "./render/icon-options"
import type { WidgetDef, WidgetNode } from "./types"

function node(type: string, props: Record<string, unknown>): WidgetNode {
  return { id: uid("w"), type, props }
}

/** Shared form; ERP adapter: formKey = Marketing form slug (bazaryabi ← forms). */
export const FORM_WIDGET: WidgetDef = sharedFormWidget({ formKeyLabel: "نامک فرم (بازاریابی ← فرم‌ها)", fieldsLabel: "فیلدها (خالی = فیلدهای خود فرم در بازاریابی ← فرم‌ها)" })

/** Shared landing widgets + ERP-only rahn-estimate. */
export const CONTENT_WIDGETS: WidgetDef[] = [
  ...sharedContentWidgets(ICON_OPTIONS),
  {
    type: "rahn-estimate",
    label: "برآورد قیمت (رهن درصدی)",
    category: "content",
    fields: [
      { kind: "text", key: "title", label: "عنوان (اختیاری)" },
      { kind: "text", key: "token", label: "توکن اشتراک پیشنهاد (/rahn/…)" },
    ],
    create: () => node("rahn-estimate", { title: "", token: "" }),
  },
]
