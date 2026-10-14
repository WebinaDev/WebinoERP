import type { FieldSpec as SchemaField } from "@/themes/webina-corporate-v1/site-content"

export const BUILDER_VERSION = 1 as const

export type DeviceMode = "desktop" | "tablet" | "mobile"

export type BoxEdges = {
  top?: string
  right?: string
  bottom?: string
  left?: string
}

export type StyleProps = {
  margin?: BoxEdges
  padding?: BoxEdges
  color?: string
  fontSize?: string
  fontWeight?: string
  lineHeight?: string
  textAlign?: "start" | "center" | "end"
  background?: string
  backgroundImage?: string
  borderWidth?: string
  borderStyle?: "none" | "solid" | "dashed"
  borderColor?: string
  radius?: string
  minHeight?: string
  maxWidth?: string
  gap?: string
  hidden?: boolean
}

export type ResponsiveStyle = {
  base?: StyleProps
  tablet?: StyleProps
  mobile?: StyleProps
}

export type WidgetNode = {
  id: string
  type: string
  props: Record<string, unknown>
  style?: ResponsiveStyle
  /** Nested sections for the container widget. */
  children?: SectionNode[]
}

export type ColumnNode = {
  id: string
  span: number
  tabletSpan?: number
  mobileSpan?: number
  style?: ResponsiveStyle
  widgets: WidgetNode[]
}

export type SectionNode = {
  id: string
  fullWidth?: boolean
  style?: ResponsiveStyle
  columns: ColumnNode[]
}

export type BuilderDocument = {
  version: typeof BUILDER_VERSION
  sections: SectionNode[]
  /** Extra CSS from an imported Elementor document. Node styles stay in `style`. */
  css?: string
  source?: string
  page_settings?: Record<string, unknown>
}

export type RuntimeContext = {
  siteName?: string
  logoUrl?: string | null
  productSlug?: string
  categorySlug?: string
  /** Published loop-item template rendered inside product grids. */
  loopDocument?: BuilderDocument
  /** Server-fetched public API data keyed by path (used by «وبینا» widgets). */
  prefetch?: Record<string, unknown>
}

export type EditorApi = {
  selectedId: string | null
  onSelect: (id: string) => void
  onInline: (id: string, key: string, value: string) => void
  onDropNew: (columnId: string, index: number, type: string) => void
  onDropMove: (columnId: string, index: number, nodeId: string) => void
  onResize: (columnId: string, span: number) => void
}

export const DND_WIDGET = "application/x-webino-widget"
export const DND_NODE = "application/x-webino-node"

export type FieldDef =
  | { kind: "text" | "textarea" | "color" | "url"; key: string; label: string }
  | { kind: "number"; key: string; label: string; min?: number; max?: number }
  | { kind: "select"; key: string; label: string; options: { value: string; label: string }[] }
  | { kind: "switch"; key: string; label: string }
  /* Extended controls ported from WebinoDashboard (editor/fields.tsx). */
  | { kind: "media"; key: string; label: string; accept?: "image" | "video" | "all" }
  | { kind: "icon"; key: string; label: string; options: { value: string; label: string }[] }
  /** URL plus `${key}NewTab` / `${key}Nofollow` switches. */
  | { kind: "link"; key: string; label: string }
  | { kind: "slider"; key: string; label: string; min: number; max: number; step?: number; unit?: string }
  | { kind: "date" | "time" | "datetime"; key: string; label: string }
  /** Rows stored in the legacy `a|b|c` line format so renderers keep reading strings. */
  | { kind: "repeater"; key: string; label: string; fields: RepeaterSubField[] }

export type RepeaterSubField = { key: string; label: string; kind: "text" | "url" | "media" | "icon" | "textarea" | "select"; options?: { value: string; label: string }[] }

export type WidgetCategory = "layout" | "basic" | "content" | "commerce" | "chrome" | "corporate"

export type WidgetDef = {
  type: string
  label: string
  category: WidgetCategory
  fields: FieldDef[]
  /** Rich schema (lists, nested items) edited with the shared SchemaForm instead of `fields`. */
  schema?: SchemaField[]
  create: () => WidgetNode
}
