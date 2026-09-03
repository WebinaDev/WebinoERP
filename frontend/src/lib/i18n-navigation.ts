export { default as Link } from "next/link"
export { redirect, usePathname, useRouter } from "next/navigation"

/** Cookie-based locale — path is never prefixed. */
export function getPathname(href: string): string {
  return href
}
