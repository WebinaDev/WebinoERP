"use client"

/* Content widgets for landing/service pages (ERP-native, not in WebinoDashboard yet):
 * section heading, numbered steps/timeline, icon cards grid, trust badges and CTA banner.
 * Repeater props use the shared legacy line format (`a|b|c` per line), so they edit with the
 * same RepeaterInput as the ported Dashboard widgets. */

import { Clock } from "lucide-react"
import Link from "next/link"

import { linkAttrs, parseRows, propStr } from "../props"
import type { WidgetNode } from "../types"
import { WIcon } from "./extra-widgets"
import "./content-widgets.css"

function safeHref(href: string, fallback = "#"): string {
  const v = href.trim()
  if (!v) return fallback
  return /^(https?:|mailto:|tel:|\/|#)/i.test(v) ? v : fallback
}

const FA_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
const faNum = (n: number) => String(n).replace(/\d/g, (d) => FA_DIGITS[Number(d)])

function align(widget: WidgetNode): string {
  const value = propStr(widget.props, "align", "center")
  return value === "start" ? "wbx-align-start" : value === "end" ? "wbx-align-end" : "wbx-align-center"
}

export function SectionHeadingWidget({ widget }: { widget: WidgetNode }) {
  const eyebrow = propStr(widget.props, "eyebrow")
  const title = propStr(widget.props, "title")
  const subtitle = propStr(widget.props, "subtitle")
  const text = propStr(widget.props, "text")
  const tag = propStr(widget.props, "tag", "h2")
  const Tag = (tag === "h1" || tag === "h3" ? tag : "h2") as "h1" | "h2" | "h3"
  return (
    <header className={`wbx-heading ${align(widget)} wbx-heading-${Tag}`}>
      {eyebrow ? <span className="wbx-eyebrow">{eyebrow}</span> : null}
      {title ? <Tag className="wbx-heading-title">{title}</Tag> : null}
      {subtitle ? <p className="wbx-heading-subtitle">{subtitle}</p> : null}
      {text ? <p className="wbx-heading-text">{text}</p> : null}
    </header>
  )
}

export function StepsWidget({ widget }: { widget: WidgetNode }) {
  const rows = parseRows(widget.props.items, ["title", "text"]).filter((row) => row.title)
  const vertical = propStr(widget.props, "layout", "horizontal") === "vertical"
  const note = propStr(widget.props, "note")
  if (!rows.length) return <p className="wbx-empty">مراحل را در تنظیمات ویجت وارد کنید (عنوان|توضیح).</p>
  return (
    <div className="wbx-steps-wrap">
      <ol className={`wbx-steps ${vertical ? "wbx-steps-vertical" : "wbx-steps-horizontal"}`} style={{ ["--wbx-count" as string]: rows.length }}>
        {rows.map((row, index) => (
          <li key={index} className="wbx-step">
            <span className="wbx-step-num" aria-hidden="true">{faNum(index + 1)}</span>
            <div className="wbx-step-body">
              <h3 className="wbx-step-title">
                <span className="sr-only">مرحله {faNum(index + 1)}: </span>
                {row.title}
              </h3>
              {row.text ? <p className="wbx-step-text">{row.text}</p> : null}
            </div>
          </li>
        ))}
      </ol>
      {note ? (
        <p className="wbx-note">
          <Clock size={18} aria-hidden="true" />
          <span>{note}</span>
        </p>
      ) : null}
    </div>
  )
}

export function IconCardsWidget({ widget }: { widget: WidgetNode }) {
  const rows = parseRows(widget.props.items, ["icon", "title", "text"]).filter((row) => row.title || row.text)
  const columns = Math.max(1, Math.min(4, Number(propStr(widget.props, "columns", "3")) || 3))
  const variant = propStr(widget.props, "variant", "card")
  if (!rows.length) return <p className="wbx-empty">کارت‌ها را در تنظیمات ویجت وارد کنید (آیکن|عنوان|متن).</p>
  return (
    <ul className={`wbx-cards wbx-cards-${variant}`} style={{ ["--wbx-cols" as string]: columns }}>
      {rows.map((row, index) => (
        <li key={index} className="wbx-card">
          <span className="wbx-card-icon">
            <WIcon name={row.icon || (variant === "check" ? "check" : "badge")} size={variant === "check" ? 18 : 22} />
          </span>
          <span className="wbx-card-body">
            {row.title ? <strong className="wbx-card-title">{row.title}</strong> : null}
            {row.text ? <span className="wbx-card-text">{row.text}</span> : null}
          </span>
        </li>
      ))}
    </ul>
  )
}

export function BadgesWidget({ widget }: { widget: WidgetNode }) {
  const rows = parseRows(widget.props.items, ["icon", "text"]).filter((row) => row.text)
  if (!rows.length) return null
  return (
    <ul className={`wbx-badges ${align(widget)}`}>
      {rows.map((row, index) => (
        <li key={index} className="wbx-badge">
          <WIcon name={row.icon || "check-circle"} size={16} />
          <span>{row.text}</span>
        </li>
      ))}
    </ul>
  )
}

export function CtaBannerWidget({ widget }: { widget: WidgetNode }) {
  const title = propStr(widget.props, "title")
  const text = propStr(widget.props, "text")
  const label = propStr(widget.props, "label")
  const href = propStr(widget.props, "href", "#")
  const tone = propStr(widget.props, "tone", "primary")
  return (
    <div className={`wbx-cta wbx-cta-${tone}`}>
      {title ? <h2 className="wbx-cta-title">{title}</h2> : null}
      {text ? <p className="wbx-cta-text">{text}</p> : null}
      {label ? (
        <Link href={safeHref(href)} className="wbx-cta-btn" {...linkAttrs(widget.props, "href")}>
          {label}
        </Link>
      ) : null}
    </div>
  )
}
