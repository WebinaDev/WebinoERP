import { describe, expect, it, vi } from "vitest"
import { createGlobalsWriteQueue, publishAfterGlobals } from "./publish-flow"

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

it("serializes pending saves around publishing", async () => {
  const enqueue = createGlobalsWriteQueue()
  const order: string[] = []
  let release!: () => void
  const wait = new Promise<void>((resolve) => { release = resolve })
  const oldSave = enqueue(async () => { order.push("old-start"); await wait; order.push("old-end") })
  const publish = enqueue(async () => { order.push("publish-put"); await Promise.resolve(); order.push("publish-post") })
  const newSave = enqueue(async () => { order.push("new-save") })
  await Promise.resolve()
  release()
  await Promise.all([oldSave, publish, newSave])
  expect(order).toEqual(["old-start", "old-end", "publish-put", "publish-post", "new-save"])
})

it("continues after a failed globals write", async () => {
  const enqueue = createGlobalsWriteQueue()
  const failed = enqueue(async () => { throw new Error("failed") })
  const next = enqueue(async () => "recovered")
  await expect(failed).rejects.toThrow("failed")
  expect(await next).toBe("recovered")
})
