import { renderToStaticMarkup } from "react-dom/server"
import { expect, test, vi } from "vitest"
import { AccordionWidget, BannerGridWidget, IconListWidget, TabsWidget } from "./extra-widgets"

vi.mock("next-intl", () => ({ useLocale: () => "en" }))
vi.mock("next/navigation", () => ({ useRouter: () => ({}) }))
vi.mock("next/link", () => ({ default: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a> }))
vi.mock("../storefront/use-catalog", () => ({ useCatalog: () => ({}), useProductQuery: () => ({}) }))
vi.mock("../storefront/ui", () => ({ StoreProductCard: () => null }))
vi.mock("./runtime", () => ({ useIsClassicSkin: () => false }))
vi.mock("@/themes/ecommerce-classic/components/ClassicHome", () => ({ ClassicProductRow: () => null }))
vi.mock("./img", () => ({ Img: ({ src, alt }: { src: string; alt: string }) => <img src={src} alt={alt} /> }))

test("widgets render repeater data from structured JSON", () => {
  const banner = renderToStaticMarkup(<BannerGridWidget widget={{ id: "b", type: "banner-grid", props: { items: [{ image: "/image.jpg", href: "/shop", alt: "Banner" }] } }} />)
  expect(banner).toContain('href="/shop"')
  expect(banner).toContain('alt="Banner"')
  const tabs = renderToStaticMarkup(<TabsWidget widget={{ id: "t", type: "tabs", props: { items: [{ title: "First", body: "Details" }] } }} />)
  expect(tabs).toContain("First")
  expect(tabs).toContain("Details")
  const accordion = renderToStaticMarkup(<AccordionWidget widget={{ id: "a", type: "accordion", props: { items: [{ q: "Question", a: "Answer" }], firstOpen: true } }} />)
  expect(accordion).toContain("Question")
  expect(accordion).toContain("Answer")
  const icons = renderToStaticMarkup(<IconListWidget widget={{ id: "i", type: "icon-list", props: { items: [{ icon: "star", text: "Feature", href: "/feature" }] } }} />)
  expect(icons).toContain("Feature")
  expect(icons).toContain('href="/feature"')
})
