/* Widget groups: post lists and galleries.
 * Each group shows one canonical widget in the library; the other types become its "display style" options in the
 * inspector. Every member type stays registered with its own renderer, so saved pages render exactly as before. */
import type { WidgetNode } from "./types"

export type WidgetGroup = { id: string; label: string; canonical: string; members: { type: string; label: string }[] }

export const WIDGET_GROUPS: WidgetGroup[] = [
  { id: "post-list", label: "فهرست مطالب", canonical: "blog-posts", members: [
    { type: "blog-posts", label: "شبکه یا فهرست ساده" },
    { type: "blog-list", label: "کارت با زمان مطالعه و فیلتر موضوع" },
    { type: "blog-slider", label: "اسلایدر" },
  ] },
  { id: "gallery", label: "گالری", canonical: "image-gallery", members: [
    { type: "image-gallery", label: "شبکه تصاویر" },
    { type: "image-carousel", label: "چرخ‌فلک تصاویر" },
  ] },
]

const groupOf = new Map<string, WidgetGroup>()
for (const g of WIDGET_GROUPS) for (const m of g.members) groupOf.set(m.type, g)

export function widgetGroup(type: string): WidgetGroup | undefined {
  return groupOf.get(type)
}

/** Library/Finder filter: hide group members other than the canonical one (they stay reachable via the inspector). */
export function isLibraryWidget(type: string): boolean {
  const g = groupOf.get(type)
  return !g || g.canonical === type
}

/** Switch a widget to another style in its group, keeping the props the user already set. */
export function switchGroupMember(widget: WidgetNode, nextType: string, create: (type: string) => WidgetNode | null): WidgetNode {
  const group = groupOf.get(widget.type)
  if (!group || groupOf.get(nextType) !== group || nextType === widget.type) return widget
  const fresh = create(nextType)
  if (!fresh) return widget
  const props = { ...fresh.props, ...widget.props }
  if (group.id === "gallery" && typeof widget.props.images === "string") {
    const hasLinks = widget.props._webinoGalleryHasLinks === true
    props.images = widget.props.images.split("\n").map((line) => {
      if (!line.trim()) return line
      const [src, second = "", third = "", ...rest] = line.split("|")
      if (nextType === "image-gallery") return [src, third, second, ...rest].join("|")
      return hasLinks ? [src, third, second, ...rest].join("|") : [src, "", second, third, ...rest].join("|")
    }).join("\n")
    if (nextType === "image-gallery") props._webinoGalleryHasLinks = true
  }
  return { ...widget, type: nextType, props, children: widget.children ?? fresh.children }
}
