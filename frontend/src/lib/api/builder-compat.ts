/**
 * Dashboard-compatible thin re-export so ported builder / storefront code can `import { api, ApiError } from "@/lib/api"`.
 * ERP's axios client lives in api-client; builder-api already strips the `/api` prefix.
 */
import axios from "axios"

import { api as builderApi } from "@/lib/builder-api"
import { getAxiosMessage } from "@/lib/api-helpers"

export type ApiOptions = {
  method?: string
  json?: unknown
  /** FormData / raw body (uploads). */
  body?: BodyInit | null
  headers?: Record<string, string>
  signal?: AbortSignal
}

export class ApiError extends Error {
  status: number
  body: unknown
  constructor(message: string, status: number, body?: unknown) {
    super(message)
    this.name = "ApiError"
    this.status = status
    this.body = body
  }
}

export async function api<T>(path: string, opts: ApiOptions = {}): Promise<T> {
  try {
    return await builderApi<T>(path, opts)
  } catch (err) {
    if (axios.isAxiosError(err)) {
      throw new ApiError(getAxiosMessage(err) || err.message, err.response?.status ?? 0, err.response?.data)
    }
    throw err
  }
}

export async function apiDownload(path: string, filename: string): Promise<void> {
  const data = await api<Blob | string>(path, { method: "GET", headers: { Accept: "text/csv, application/octet-stream, application/json" } })
  const blob = data instanceof Blob ? data : new Blob([typeof data === "string" ? data : JSON.stringify(data)])
  const url = URL.createObjectURL(blob)
  const a = document.createElement("a")
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}
