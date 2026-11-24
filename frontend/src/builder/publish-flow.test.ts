import { describe, expect, it, vi } from "vitest"
import { publishAfterGlobals } from "./publish-flow"

describe("publish dependencies", () => {
  it("publishes globals before the document", async () => {
    const order: string[] = []
    const result = await publishAfterGlobals(async () => { order.push("globals"); return true }, async () => { order.push("document") })
    expect(result).toBe(true)
    expect(order).toEqual(["globals", "document"])
  })
  it("does not publish the document when globals fail", async () => {
    const publishDocument = vi.fn()
    expect(await publishAfterGlobals(async () => false, publishDocument)).toBe(false)
    expect(publishDocument).not.toHaveBeenCalled()
  })
  it("propagates a document publication error", async () => {
    await expect(publishAfterGlobals(async () => true, async () => { throw new Error("failed") })).rejects.toThrow("failed")
  })
})
