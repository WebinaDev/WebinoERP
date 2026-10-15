"use client"

/* Renderers for @webina/ui v0.2 shared gap widgets (team, media, layout chrome, auth shells). */

import Link from "next/link"
import { useCallback, useEffect, useMemo, useRef, useState } from "react"

import { linkAttrs, parseLinks, parseRows, propBool, propNum, propStr } from "../props"
import type { WidgetNode } from "../types"
import { embedVideoSrc } from "./sanitize-html"
import { Img } from "./img"
import "./gap-widgets.css"

function safeHref(href: string, fallback = "#"): string {
  const v = href.trim()
  if (!v) return fallback
  return /^(https?:|mailto:|tel:|\/|#)/i.test(v) ? v : fallback
}

function CaptchaPlaceholder() {
  return (
    <div className="wbg-captcha" role="group" aria-label="کپچا">
      <label className="wbg-captcha-row">
        <input type="checkbox" name="_captcha" value="1" required />
        <span>من ربات نیستم</span>
      </label>
    </div>
  )
}

/* ——— Team ——— */

type TeamMember = { name: string; role: string; photo: string; quote: string; link: string }

export function TeamGridWidget({ widget }: { widget: WidgetNode }) {
  const title = propStr(widget.props, "title")
  const collection = propStr(widget.props, "collection")
  const layout = propStr(widget.props, "layout", "grid")
  const columns = Math.max(1, Math.min(4, Number(propStr(widget.props, "columns", "3")) || 3))
  const manual = parseRows(widget.props.members, ["name", "role", "photo", "quote", "link"]).filter((r) => r.name)
  const [remote, setRemote] = useState<TeamMember[] | null>(null)

  useEffect(() => {
    if (!collection) {
      setRemote(null)
      return
    }
    let cancelled = false
    void fetch(`/api/v1/public/collections/${encodeURIComponent(collection)}`, { headers: { Accept: "application/json" } })
      .then(async (res) => {
        const json = (await res.json().catch(() => null)) as { data?: unknown } | null
        const rows = Array.isArray(json?.data) ? json!.data! : Array.isArray(json) ? json : []
        if (cancelled) return
        setRemote(
          rows.flatMap((item) => {
            if (!item || typeof item !== "object") return []
            const row = item as Record<string, unknown>
            const name = typeof row.name === "string" ? row.name : typeof row.title === "string" ? row.title : ""
            if (!name) return []
            return [{
              name,
              role: typeof row.role === "string" ? row.role : typeof row.job === "string" ? row.job : "",
              photo: typeof row.photo === "string" ? row.photo : typeof row.image === "string" ? row.image : "",
              quote: typeof row.quote === "string" ? row.quote : typeof row.bio === "string" ? row.bio : "",
              link: typeof row.link === "string" ? row.link : typeof row.href === "string" ? row.href : "",
            }]
          }),
        )
      })
      .catch(() => {
        if (!cancelled) setRemote([])
      })
    return () => {
      cancelled = true
    }
  }, [collection])

  const members: TeamMember[] = collection ? remote ?? [] : manual
  if (!members.length) {
    return <p className="wbg-empty">{collection ? (remote === null ? "در حال بارگذاری…" : "عضوی در کالکشن نیست.") : "اعضا را در تنظیمات ویجت وارد کنید."}</p>
  }

  return (
    <div className={`wbg-team wbg-team-${layout}`}>
      {title ? <h2 className="wbg-team-title">{title}</h2> : null}
      <ul className="wbg-team-list" style={{ ["--wbg-cols" as string]: columns }}>
        {members.map((m, index) => {
          const body = (
            <>
              {m.photo ? <Img src={m.photo} alt={m.name} className="wbg-team-photo" /> : <span className="wbg-team-photo wbg-team-photo-empty" aria-hidden />}
              <span className="wbg-team-meta">
                <strong className="wbg-team-name">{m.name}</strong>
                {m.role ? <span className="wbg-team-role">{m.role}</span> : null}
                {m.quote ? <span className="wbg-team-quote">{m.quote}</span> : null}
              </span>
            </>
          )
          return (
            <li key={`${m.name}-${index}`} className="wbg-team-card">
              {m.link ? (
                <Link href={safeHref(m.link)} className="wbg-team-link">{body}</Link>
              ) : (
                body
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

/* ——— Media ——— */

export function VideoEmbedWidget({ widget }: { widget: WidgetNode }) {
  const url = propStr(widget.props, "url")
  const title = propStr(widget.props, "title", "ویدیو")
  const poster = propStr(widget.props, "poster")
  const aspect = propStr(widget.props, "aspect", "16/9")
  const autoplay = propBool(widget.props, "autoplay")
  const muted = propBool(widget.props, "muted", true)
  const loop = propBool(widget.props, "loop")
  const controls = propBool(widget.props, "controls", true)
  const embed = embedVideoSrc(url)

  if (!url) return <div className="wbg-empty wbg-media-empty">نشانی یوتیوب یا آپارات را وارد کنید.</div>

  let src = embed
  if (src && autoplay) {
    try {
      const u = new URL(src)
      u.searchParams.set("autoplay", "1")
      if (muted) u.searchParams.set("mute", "1")
      if (loop) u.searchParams.set("loop", "1")
      src = u.toString()
    } catch {
      /* keep */
    }
  }

  return (
    <div className="wbg-video" style={{ aspectRatio: aspect }}>
      {src ? (
        <iframe title={title} src={src} allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowFullScreen />
      ) : (
        <video src={url} poster={poster || undefined} controls={controls} autoPlay={autoplay} muted={muted || autoplay} loop={loop} playsInline className="wbg-video-el" />
      )}
    </div>
  )
}

export function AudioBlockWidget({ widget }: { widget: WidgetNode }) {
  const src = propStr(widget.props, "src")
  const title = propStr(widget.props, "title")
  const caption = propStr(widget.props, "caption")
  if (!src) return <p className="wbg-empty">نشانی فایل صوت را وارد کنید.</p>
  return (
    <figure className="wbg-audio">
      {title ? <figcaption className="wbg-audio-title">{title}</figcaption> : null}
      <audio src={src} controls autoPlay={propBool(widget.props, "autoplay")} loop={propBool(widget.props, "loop")} preload="metadata" />
      {caption ? <p className="wbg-audio-caption">{caption}</p> : null}
    </figure>
  )
}

export function BeforeAfterWidget({ widget }: { widget: WidgetNode }) {
  const before = propStr(widget.props, "before")
  const after = propStr(widget.props, "after")
  const beforeLabel = propStr(widget.props, "beforeLabel", "قبل")
  const afterLabel = propStr(widget.props, "afterLabel", "بعد")
  const orientation = propStr(widget.props, "orientation", "horizontal")
  const start = Math.max(0, Math.min(100, propNum(widget.props, "start", 50)))
  const [pos, setPos] = useState(start)
  const dragging = useRef(false)
  const box = useRef<HTMLDivElement>(null)

  const onMove = useCallback(
    (clientX: number, clientY: number) => {
      const el = box.current
      if (!el) return
      const rect = el.getBoundingClientRect()
      if (orientation === "vertical") {
        const y = ((clientY - rect.top) / rect.height) * 100
        setPos(Math.max(0, Math.min(100, y)))
      } else {
        const x = ((clientX - rect.left) / rect.width) * 100
        setPos(Math.max(0, Math.min(100, x)))
      }
    },
    [orientation],
  )

  useEffect(() => {
    const up = () => {
      dragging.current = false
    }
    const move = (event: PointerEvent) => {
      if (!dragging.current) return
      onMove(event.clientX, event.clientY)
    }
    window.addEventListener("pointerup", up)
    window.addEventListener("pointermove", move)
    return () => {
      window.removeEventListener("pointerup", up)
      window.removeEventListener("pointermove", move)
    }
  }, [onMove])

  if (!before || !after) return <p className="wbg-empty">تصاویر قبل و بعد را انتخاب کنید.</p>

  const vertical = orientation === "vertical"
  return (
    <div
      ref={box}
      className={`wbg-ba ${vertical ? "wbg-ba-v" : "wbg-ba-h"}`}
      onPointerDown={(event) => {
        dragging.current = true
        ;(event.target as HTMLElement).setPointerCapture?.(event.pointerId)
        onMove(event.clientX, event.clientY)
      }}
    >
      <Img src={after} alt={afterLabel} className="wbg-ba-img wbg-ba-after" />
      <div className="wbg-ba-before-wrap" style={vertical ? { height: `${pos}%` } : { width: `${pos}%` }}>
        <Img src={before} alt={beforeLabel} className="wbg-ba-img wbg-ba-before" />
      </div>
      <div className="wbg-ba-handle" style={vertical ? { top: `${pos}%` } : { insetInlineStart: `${pos}%` }} role="slider" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(pos)} aria-label="مقایسه قبل و بعد" tabIndex={0} />
      <span className="wbg-ba-label wbg-ba-label-before">{beforeLabel}</span>
      <span className="wbg-ba-label wbg-ba-label-after">{afterLabel}</span>
    </div>
  )
}

export function LottieWidget({ widget }: { widget: WidgetNode }) {
  const src = propStr(widget.props, "src")
  const height = propNum(widget.props, "height", 200)
  const ariaLabel = propStr(widget.props, "ariaLabel", "انیمیشن")
  if (!src) return <p className="wbg-empty">نشانی JSON لوتی را وارد کنید.</p>
  // No hard dependency on lottie-web: expose data attrs for enhancement; show accessible fallback.
  return (
    <div
      className="wbg-lottie"
      style={{ height }}
      role="img"
      aria-label={ariaLabel}
      data-wbg-lottie={src}
      data-wbg-lottie-autoplay={propBool(widget.props, "autoplay", true) ? "1" : "0"}
      data-wbg-lottie-loop={propBool(widget.props, "loop", true) ? "1" : "0"}
      data-wbg-lottie-speed={String(propNum(widget.props, "speed", 1))}
    >
      <a className="wbg-lottie-fallback" href={src} target="_blank" rel="noopener noreferrer">
        {ariaLabel} (Lottie)
      </a>
    </div>
  )
}

/* ——— Auth shells (platform wires real auth; UI + events here) ——— */

export function LoginFormWidget({ widget }: { widget: WidgetNode }) {
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [remember, setRemember] = useState(false)
  const [pending, setPending] = useState(false)
  const [error, setError] = useState("")
  const [done, setDone] = useState(false)
  const captcha = propBool(widget.props, "captcha")
  const redirect = propStr(widget.props, "redirect", "/account")

  if (done) {
    return <p className="wbg-form-success" role="status">{propStr(widget.props, "success") || "وارد شدید."}</p>
  }

  return (
    <form
      className="wbg-auth"
      onSubmit={(event) => {
        event.preventDefault()
        setPending(true)
        setError("")
        window.dispatchEvent(
          new CustomEvent("wb:auth-login", {
            detail: { email, password, remember, redirect, widgetId: widget.id },
          }),
        )
        // Soft success if platform handler navigates; otherwise show message after short wait
        window.setTimeout(() => {
          setPending(false)
          if (typeof window !== "undefined" && !window.location.pathname.includes(redirect.replace(/^\//, ""))) {
            // stay; platform may have handled
          }
          setDone(true)
        }, 400)
      }}
    >
      {propStr(widget.props, "title") ? <h3 className="wbg-auth-title">{propStr(widget.props, "title")}</h3> : null}
      {propStr(widget.props, "intro") ? <p className="wbg-auth-intro">{propStr(widget.props, "intro")}</p> : null}
      <label className="wbg-field">
        <span>ایمیل</span>
        <input type="email" autoComplete="username" required value={email} onChange={(e) => setEmail(e.target.value)} />
      </label>
      <label className="wbg-field">
        <span>رمز عبور</span>
        <input type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} />
      </label>
      {propBool(widget.props, "showRemember", true) ? (
        <label className="wbg-check">
          <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
          <span>مرا به خاطر بسپار</span>
        </label>
      ) : null}
      {captcha ? <CaptchaPlaceholder /> : null}
      {error ? <p className="wbg-form-error">{error}</p> : null}
      <button type="submit" className="wb-btn wb-btn-primary" disabled={pending}>
        {pending ? "…" : propStr(widget.props, "submit", "ورود")}
      </button>
      <div className="wbg-auth-links">
        {propStr(widget.props, "forgotLabel") ? (
          <Link href={safeHref(propStr(widget.props, "forgotHref", "#"))} {...linkAttrs(widget.props, "forgotHref")}>
            {propStr(widget.props, "forgotLabel")}
          </Link>
        ) : null}
        {propStr(widget.props, "registerLabel") ? (
          <Link href={safeHref(propStr(widget.props, "registerHref", "#"))} {...linkAttrs(widget.props, "registerHref")}>
            {propStr(widget.props, "registerLabel")}
          </Link>
        ) : null}
      </div>
    </form>
  )
}

export function RegisterFormWidget({ widget }: { widget: WidgetNode }) {
  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [phone, setPhone] = useState("")
  const [password, setPassword] = useState("")
  const [termsOk, setTermsOk] = useState(false)
  const [pending, setPending] = useState(false)
  const [done, setDone] = useState(false)
  const captcha = propBool(widget.props, "captcha")

  if (done) {
    return <p className="wbg-form-success" role="status">{propStr(widget.props, "success", "حساب شما ساخته شد.")}</p>
  }

  return (
    <form
      className="wbg-auth"
      onSubmit={(event) => {
        event.preventDefault()
        if (propBool(widget.props, "terms", true) && !termsOk) return
        setPending(true)
        window.dispatchEvent(
          new CustomEvent("wb:auth-register", {
            detail: {
              name,
              email,
              phone,
              password,
              redirect: propStr(widget.props, "redirect", "/account"),
              widgetId: widget.id,
            },
          }),
        )
        window.setTimeout(() => {
          setPending(false)
          setDone(true)
        }, 400)
      }}
    >
      {propStr(widget.props, "title") ? <h3 className="wbg-auth-title">{propStr(widget.props, "title")}</h3> : null}
      {propStr(widget.props, "intro") ? <p className="wbg-auth-intro">{propStr(widget.props, "intro")}</p> : null}
      {propBool(widget.props, "showName", true) ? (
        <label className="wbg-field">
          <span>نام</span>
          <input type="text" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} />
        </label>
      ) : null}
      <label className="wbg-field">
        <span>ایمیل</span>
        <input type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
      </label>
      {propBool(widget.props, "showPhone", true) ? (
        <label className="wbg-field">
          <span>موبایل</span>
          <input type="tel" autoComplete="tel" value={phone} onChange={(e) => setPhone(e.target.value)} />
        </label>
      ) : null}
      <label className="wbg-field">
        <span>رمز عبور</span>
        <input type="password" autoComplete="new-password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} />
      </label>
      {propBool(widget.props, "terms", true) ? (
        <label className="wbg-check">
          <input type="checkbox" required checked={termsOk} onChange={(e) => setTermsOk(e.target.checked)} />
          <span>
            {propStr(widget.props, "termsHref") ? (
              <Link href={safeHref(propStr(widget.props, "termsHref"))} {...linkAttrs(widget.props, "termsHref")}>
                {propStr(widget.props, "termsLabel", "قوانین و شرایط را می‌پذیرم")}
              </Link>
            ) : (
              propStr(widget.props, "termsLabel", "قوانین و شرایط را می‌پذیرم")
            )}
          </span>
        </label>
      ) : null}
      {captcha ? <CaptchaPlaceholder /> : null}
      <button type="submit" className="wb-btn wb-btn-primary" disabled={pending}>
        {pending ? "…" : propStr(widget.props, "submit", "ایجاد حساب")}
      </button>
      {propStr(widget.props, "loginLabel") ? (
        <div className="wbg-auth-links">
          <Link href={safeHref(propStr(widget.props, "loginHref", "#"))} {...linkAttrs(widget.props, "loginHref")}>
            {propStr(widget.props, "loginLabel")}
          </Link>
        </div>
      ) : null}
    </form>
  )
}

/* ——— Layout shells (children rendered by DocumentView) ——— */

export function OffCanvasWidget({ widget }: { widget: WidgetNode }) {
  const [open, setOpen] = useState(false)
  const title = propStr(widget.props, "title", "منو")
  const side = propStr(widget.props, "side", "start")
  const width = propStr(widget.props, "width", "md")
  const body = propStr(widget.props, "body")
  const ctaLabel = propStr(widget.props, "ctaLabel")

  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false)
    }
    window.addEventListener("keydown", onKey)
    return () => window.removeEventListener("keydown", onKey)
  }, [open])

  return (
    <div className="wbg-offcanvas">
      <button type="button" className="wb-btn wb-btn-outline" onClick={() => setOpen(true)}>
        {propStr(widget.props, "triggerLabel", "باز کردن")}
      </button>
      {open ? (
        <div className="wbg-offcanvas-root" role="dialog" aria-modal="true" aria-label={title}>
          <button type="button" className="wbg-offcanvas-backdrop" aria-label="بستن" onClick={() => setOpen(false)} />
          <aside className={`wbg-offcanvas-panel wbg-offcanvas-${side} wbg-offcanvas-w-${width}`}>
            <header className="wbg-offcanvas-head">
              <h3>{title}</h3>
              <button type="button" className="wbg-offcanvas-close" onClick={() => setOpen(false)} aria-label="بستن">
                ×
              </button>
            </header>
            {body ? <p className="wbg-offcanvas-body">{body}</p> : null}
            {ctaLabel ? (
              <Link href={safeHref(propStr(widget.props, "ctaHref", "#"))} className="wb-btn wb-btn-primary" {...linkAttrs(widget.props, "ctaHref")} onClick={() => setOpen(false)}>
                {ctaLabel}
              </Link>
            ) : null}
          </aside>
        </div>
      ) : null}
    </div>
  )
}

/* ——— Chrome ——— */

export function SiteHeaderWidget({ widget, siteName, logoUrl }: { widget: WidgetNode; siteName?: string; logoUrl?: string | null }) {
  const logo = propStr(widget.props, "logo") || logoUrl || ""
  const name = propStr(widget.props, "siteName") || siteName || ""
  const links = parseLinks(widget.props.links, [])
  const tone = propStr(widget.props, "tone", "light")
  const sticky = propBool(widget.props, "sticky", true)
  const ctaLabel = propStr(widget.props, "ctaLabel")

  return (
    <header className={`wbg-header wbg-header-${tone} ${sticky ? "wbg-header-sticky" : ""}`}>
      <div className="wbg-header-inner">
        <Link href="/" className="wbg-header-brand">
          {logo ? <Img src={logo} alt={name || "لوگو"} className="wbg-header-logo" /> : null}
          {name ? <span className="wbg-header-name">{name}</span> : null}
        </Link>
        <nav className="wbg-header-nav" aria-label="منوی اصلی">
          {links.map((link) => (
            <Link key={link.href + link.label} href={safeHref(link.href)} className="wbg-header-link">
              {link.label}
            </Link>
          ))}
        </nav>
        {ctaLabel ? (
          <Link href={safeHref(propStr(widget.props, "ctaHref", "#"))} className="wb-btn wb-btn-primary wbg-header-cta" {...linkAttrs(widget.props, "ctaHref")}>
            {ctaLabel}
          </Link>
        ) : null}
      </div>
    </header>
  )
}

export function SiteFooterWidget({ widget, siteName }: { widget: WidgetNode; siteName?: string }) {
  const logo = propStr(widget.props, "logo")
  const blurb = propStr(widget.props, "blurb")
  const tone = propStr(widget.props, "tone", "dark")
  const copyright = propStr(widget.props, "copyright") || `© ${siteName || ""}`.trim()
  const columns = useMemo(() => {
    // repeater: title + links textarea; also accept legacy "title|label|href" lines packed oddly
    if (Array.isArray(widget.props.columns)) {
      return (widget.props.columns as Record<string, unknown>[]).map((col) => ({
        title: typeof col.title === "string" ? col.title : "",
        links: parseLinks(col.links, []),
      }))
    }
    if (typeof widget.props.columns === "string" && widget.props.columns.trim()) {
      // "Title|Label|/path" per line → group by title
      const groups = new Map<string, { label: string; href: string }[]>()
      for (const line of widget.props.columns.split("\n").map((l) => l.trim()).filter(Boolean)) {
        const [title, label, href] = line.split("|").map((p) => p.trim())
        if (!title || !label) continue
        const list = groups.get(title) ?? []
        list.push({ label, href: href || "/" })
        groups.set(title, list)
      }
      return [...groups.entries()].map(([title, links]) => ({ title, links }))
    }
    return []
  }, [widget.props.columns])

  const socials = parseRows(widget.props.socials, ["network", "href"]).filter((r) => r.href)

  return (
    <footer className={`wbg-footer wbg-footer-${tone}`}>
      <div className="wbg-footer-inner">
        <div className="wbg-footer-brand">
          {logo ? <Img src={logo} alt={siteName || ""} className="wbg-footer-logo" /> : null}
          {blurb ? <p>{blurb}</p> : null}
        </div>
        <div className="wbg-footer-cols">
          {columns.map((col, index) => (
            <div key={index} className="wbg-footer-col">
              {col.title ? <h4>{col.title}</h4> : null}
              <ul>
                {col.links.map((link) => (
                  <li key={link.href + link.label}>
                    <Link href={safeHref(link.href)}>{link.label}</Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        {socials.length ? (
          <ul className="wbg-footer-socials">
            {socials.map((s, index) => (
              <li key={index}>
                <a href={safeHref(s.href)} rel="noopener noreferrer" target="_blank">
                  {s.network || s.href}
                </a>
              </li>
            ))}
          </ul>
        ) : null}
      </div>
      {copyright ? <p className="wbg-footer-copy">{copyright}</p> : null}
    </footer>
  )
}
