import type { paths } from "./schema"

export type { paths } from "./schema"

export type ErpClientOptions = {
  baseUrl: string
  /** Sanctum personal access token (server-to-server). Prefer cookie cookies for browsers. */
  token?: string
  /** Send cookies (`credentials: 'include'`) for SPA / HttpOnly auth. */
  credentials?: RequestCredentials
}

export class ErpClient {
  private readonly baseUrl: string
  private readonly token?: string
  private readonly credentials: RequestCredentials

  constructor(opts: ErpClientOptions) {
    this.baseUrl = opts.baseUrl.replace(/\/$/, "")
    this.token = opts.token
    this.credentials = opts.credentials ?? (opts.token ? "omit" : "include")
  }

  private async request<T>(path: string, init: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...(init.headers as Record<string, string> | undefined),
    }
    if (this.token) {
      headers.Authorization = `Bearer ${this.token}`
    }

    const res = await fetch(`${this.baseUrl}${path}`, {
      ...init,
      credentials: this.credentials,
      headers,
    })
    const text = await res.text()
    const data = text ? JSON.parse(text) : null
    if (!res.ok) {
      throw new Error(typeof data?.message === "string" ? data.message : `HTTP ${res.status}`)
    }
    return data as T
  }

  login(email: string, password: string) {
    return this.request<unknown>("/api/v1/core/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    })
  }

  refresh() {
    return this.request<unknown>("/api/v1/core/auth/refresh", { method: "POST" })
  }

  logout() {
    return this.request<unknown>("/api/v1/core/auth/logout", { method: "POST" })
  }

  getUser() {
    return this.request<unknown>("/api/v1/core/auth/user")
  }

  openapi() {
    return this.request<Record<string, unknown>>("/api/v1/openapi.json")
  }

  api<T>(method: string, path: string, body?: unknown): Promise<T> {
    return this.request<T>(path, {
      method: method.toUpperCase(),
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    })
  }
}
