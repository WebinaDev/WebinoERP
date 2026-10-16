"use client"

/* Portfolio detail widgets (page builder): gallery with lightbox, icon info table, story card.
 * Content lives in the builder document props; nothing is hardcoded here. */

import Link from "next/link"
import type { LucideIcon } from "lucide-react"
import {
  BadgeCheck, Briefcase, Building2, Calendar, CheckCircle2, ExternalLink, Globe, Layers, Lightbulb,
  Palette, Rocket, Tag, Target, TrendingUp, Trophy, Users, Wrench,
} from "lucide-react"

import { PortfolioGallery } from "@/app/(site)/portfolio/[slug]/PortfolioGallery"
import { propStr } from "../props"
import type { WidgetNode } from "../types"

export const DETAIL_ICONS: Record<string, LucideIcon> = {
  client: Building2, globe: Globe, layers: Layers, briefcase: Briefcase, tag: Tag, trend: TrendingUp,
  check: CheckCircle2, target: Target, idea: Lightbulb, trophy: Trophy, calendar: Calendar, users: Users,
  wrench: Wrench, palette: Palette, rocket: Rocket, badge: BadgeCheck,
}

const I = ({ name, className }: { name: string; className?: string }) => {
  const C = DETAIL_ICONS[name] ?? BadgeCheck
  return <C className={className ?? "h-[18px] w-[18px]"} aria-hidden="true" />
}

const rows = (t: string) => t.split("\n").map((l) => l.trim()).filter(Boolean).map((l) => l.split("|").map((c) => c.trim()))
const safe = (h: string) => (/^(https?:|mailto:|tel:|\/|#)/i.test(h.trim()) ? h.trim() : "")

/** images: one «url|alt» per line. */
export function PortfolioGalleryWidget({ widget }: { widget: WidgetNode }) {
  const list = rows(propStr(widget.props, "images", "")).map((r) => r[0]).filter(Boolean)
  return <PortfolioGallery images={list} title={propStr(widget.props, "alt", "تصویر نمونه‌کار")} />
}

/** rows: «icon|label|value|href» per line; value with «،» stays text, «chips:» prefix renders chips split by «،». */
export function InfoTableWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const title = propStr(p, "title", "")
  const btn = propStr(p, "buttonLabel", "")
  const btnHref = safe(propStr(p, "buttonHref", ""))
  return (
    <div className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-sm">
      {title ? <h2 className="mb-3 text-lg font-extrabold text-[#22242a]">{title}</h2> : null}
      <table className="w-full text-sm">
        <tbody>
          {rows(propStr(p, "rows", "")).map(([icon = "badge", label = "", value = "", href = ""], i) => {
            const link = safe(href)
            const chips = value.startsWith("chips:") ? value.slice(6).split("،").map((s) => s.trim()).filter(Boolean) : null
            return (
              <tr key={i} className="border-b border-[#eef0f5] last:border-0">
                <th scope="row" className="w-[42%] py-3 pl-3 text-right align-top font-medium text-[#6b7080]">
                  <span className="flex items-center gap-2"><span className="text-[#0054ff]"><I name={icon} /></span>{label}</span>
                </th>
                <td className="py-3 align-top font-semibold text-[#22242a]">
                  {chips ? (
                    <ul className="flex flex-wrap gap-1.5">{chips.map((c) => <li key={c} className="rounded-full bg-[#f0f4ff] px-2.5 py-0.5 text-xs font-medium text-[#0054ff]">{c}</li>)}</ul>
                  ) : link ? (
                    <a href={link} target={link.startsWith("http") ? "_blank" : undefined} rel="noopener" dir="ltr" className="inline-flex items-center gap-1 text-[#0054ff] hover:underline">{value}<ExternalLink className="h-3.5 w-3.5" /></a>
                  ) : value}
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
      {btn && btnHref ? (
        btnHref.startsWith("http") ? (
          <a href={btnHref} target="_blank" rel="noopener" className="btn-saffron mt-5 flex w-full items-center justify-center gap-2">{btn}<ExternalLink className="h-4 w-4" /></a>
        ) : <Link href={btnHref} className="btn-saffron mt-5 flex w-full items-center justify-center">{btn}</Link>
      ) : null}
    </div>
  )
}

const TONES: Record<string, string> = {
  blue: "bg-[#f0f4ff] text-[#0054ff]", orange: "bg-[#fff1ec] text-[#e5532d]", green: "bg-[#eafaf1] text-[#16a34a]", dark: "bg-[#22242a] text-white",
}

/** Story card: icon + title + multi-paragraph text; variant «highlight» = solid brand banner. */
export function StoryCardWidget({ widget }: { widget: WidgetNode }) {
  const p = widget.props
  const lines = propStr(p, "text", "").split("\n").map((s) => s.trim()).filter(Boolean)
  const title = propStr(p, "title", "")
  const icon = propStr(p, "icon", "badge")
  if (propStr(p, "variant", "card") === "highlight") {
    return (
      <div className="flex items-start gap-3 rounded-2xl bg-[#0054ff] p-5 text-white">
        <I name={icon} className="mt-0.5 h-6 w-6 shrink-0" />
        <div>{title ? <p className="text-sm opacity-80">{title}</p> : null}{lines.map((l, i) => <p key={i} className="text-lg font-extrabold">{l}</p>)}</div>
      </div>
    )
  }
  if (propStr(p, "variant", "card") === "cta") {
    const links = [["primaryLabel", "primaryHref"], ["secondaryLabel", "secondaryHref"], ["tertiaryLabel", "tertiaryHref"]]
      .map(([l, h]) => [propStr(p, l, ""), safe(propStr(p, h, ""))]).filter(([l, h]) => l && h)
    return (
      <div className="rounded-3xl bg-[#22242a] p-6 text-white">
        {title ? <h2 className="text-lg font-extrabold">{title}</h2> : null}
        {lines.map((l, i) => <p key={i} className="mt-2 text-sm leading-7 opacity-80">{l}</p>)}
        <div className="mt-4 grid gap-2">
          {links.map(([l, h], i) => (
            <Link key={h} href={h} className={i === 0 ? "btn-saffron text-center" : "rounded-full border border-white/30 px-4 py-2 text-center text-sm font-bold hover:bg-white/10"}>{l}</Link>
          ))}
        </div>
      </div>
    )
  }
  if (propStr(p, "variant", "card") === "checklist") {
    return (
      <section className="rounded-3xl bg-[#f5f8ff] p-6 sm:p-8">
        {title ? <h2 className="mb-4 text-xl font-extrabold text-[#22242a]">{title}</h2> : null}
        <ul className="grid gap-3 sm:grid-cols-2">
          {lines.map((l) => <li key={l} className="flex items-center gap-2 rounded-xl bg-white px-4 py-3 font-medium text-[#22242a] shadow-sm"><I name={icon} className="h-5 w-5 shrink-0 text-[#0054ff]" />{l}</li>)}
        </ul>
      </section>
    )
  }
  return (
    <section className="rounded-3xl border border-[#e6e9f2] bg-white p-6 shadow-sm sm:p-8">
      <div className="mb-4 flex items-center gap-3">
        <span className={`grid h-11 w-11 place-items-center rounded-2xl ${TONES[propStr(p, "tone", "blue")] ?? TONES.blue}`}><I name={icon} className="h-5 w-5" /></span>
        {title ? <h2 className="text-xl font-extrabold text-[#22242a]">{title}</h2> : null}
      </div>
      <div className="space-y-3">{lines.map((l, i) => <p key={i} className="rich-copy leading-8 text-[#3a3d45]">{l}</p>)}</div>
    </section>
  )
}
