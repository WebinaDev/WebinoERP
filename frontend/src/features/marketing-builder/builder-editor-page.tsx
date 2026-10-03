"use client"

import { BuilderEditor } from "@/builder/editor/BuilderEditor"

export function BuilderEditorPage({ path }: { path: string }) {
  const parts = path.split("/").filter(Boolean)
  if (parts[0] === "theme-builder" && parts.length >= 3) {
    const templateKind = parts[1] || "header"
    const raw = parts[2]
    const id = raw && raw !== "new" && Number.isFinite(Number(raw)) ? Number(raw) : "new"
    return <BuilderEditor mode={{ kind: "theme", templateKind, id }} />
  }
  if (parts[0] === "builder" && parts[1] === "chrome") {
    const chrome = parts[2] === "footer" ? "footer" : "header"
    return <BuilderEditor mode={{ kind: "chrome", chrome }} />
  }
  if (path === "builder/new" || parts[2] === "new") {
    return <BuilderEditor mode={{ kind: "page", id: "new" }} />
  }
  const id = Number(parts[parts.length - 1])
  return <BuilderEditor mode={{ kind: "page", id: Number.isFinite(id) ? id : "new" }} />
}
