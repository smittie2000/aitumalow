export type RequestHeaders = Record<string, string>

export interface HttpTransport {
  get: <T>(path: string) => Promise<T>
  post: <T>(path: string, body?: unknown) => Promise<T>
  put: <T>(path: string, body?: unknown) => Promise<T>
  patch: <T>(path: string, body?: unknown) => Promise<T>
  delete: <T>(path: string) => Promise<T>
  raw: (path: string, init?: RequestInit) => Promise<Response>
}

export interface HttpTransportOptions {
  baseUrl?: string
  token?: string | (() => string | undefined)
  csrfToken?: string | null | (() => string | null)
  headers?: RequestHeaders | (() => RequestHeaders)
  credentials?: RequestCredentials
  fetch?: typeof globalThis.fetch
}

const browserGlobals = (): Record<string, unknown> => (
  typeof window === 'undefined' ? {} : window as unknown as Record<string, unknown>
)

const defaultBaseUrl = (): string => {
  const configured = browserGlobals().__AITUMALOW_API_BASE_URL__
  return typeof configured === 'string'
    ? configured
    : import.meta.env.VITE_API_BASE_URL || '/workflow-engine'
}

const defaultCsrfToken = (): string | null => {
  if (typeof document === 'undefined') return null
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? null
}

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]> | null

  constructor(status: number, errors: Record<string, string[]> | null, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export function createHttpTransport(options: HttpTransportOptions = {}): HttpTransport {
  const resolveToken = (): string | undefined => {
    if (typeof options.token === 'function') return options.token()
    if (options.token) return options.token

    const token = browserGlobals().__AITUMALOW_API_TOKEN__
    return typeof token === 'string' ? token : undefined
  }

  const resolveCsrfToken = (): string | null => {
    if (typeof options.csrfToken === 'function') return options.csrfToken()
    if (options.csrfToken !== undefined) return options.csrfToken
    return defaultCsrfToken()
  }

  const resolveHeaders = (): RequestHeaders => ({
    ...(typeof options.headers === 'function' ? options.headers() : options.headers),
  })

  const raw = async (path: string, init: RequestInit = {}): Promise<Response> => {
    const headers: RequestHeaders = {
      ...resolveHeaders(),
      ...(init.headers ? Object.fromEntries(new Headers(init.headers).entries()) : {}),
    }
    const csrf = resolveCsrfToken()
    const token = resolveToken()

    if (csrf && !headers['X-CSRF-TOKEN']) headers['X-CSRF-TOKEN'] = csrf
    if (token && !headers.Authorization) headers.Authorization = `Bearer ${token}`

    const fetcher = options.fetch ?? globalThis.fetch
    if (!fetcher) throw new Error('No fetch implementation is available for the Aitumalow SDK.')

    return fetcher(`${options.baseUrl ?? defaultBaseUrl()}${path}`, {
      ...init,
      headers,
      credentials: init.credentials ?? options.credentials ?? 'same-origin',
    })
  }

  const request = async <T>(method: string, path: string, body?: unknown): Promise<T> => {
    const res = await raw(path, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: body != null ? JSON.stringify(body) : undefined,
    })

    if (!res.ok) {
      let errors: Record<string, string[]> | null = null
      let message = `HTTP ${res.status}`
      try {
        const json = await res.json()
        errors = json.errors ?? null
        message = json.message ?? message
      } catch {
        // Ignore non-JSON error responses.
      }
      throw new ApiError(res.status, errors, message)
    }

    if (res.status === 204) return undefined as T

    return res.json()
  }

  return {
    get: <T>(path: string) => request<T>('GET', path),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
    delete: <T>(path: string) => request<T>('DELETE', path),
    raw,
  }
}

export const api = createHttpTransport()
