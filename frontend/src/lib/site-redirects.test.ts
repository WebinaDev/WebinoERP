import { describe, expect, it } from "vitest"

import { parseSiteRedirects, siteRedirectFor } from "./site-redirects"

describe("site redirects", () => {
  it("defaults to alikhabari /about → /about-poet only on that host", () => {
    const rules = parseSiteRedirects(undefined)
    expect(siteRedirectFor("alikhabari.ir", "/about/", rules)).toBe("/about-poet")
    expect(siteRedirectFor("other.ir", "/about", rules)).toBeNull()
  })
  it("parses host-less and empty config", () => {
    expect(siteRedirectFor("x.ir", "/a", parseSiteRedirects("/a=/b"))).toBe("/b")
    expect(parseSiteRedirects("")).toEqual([])
  })
})
