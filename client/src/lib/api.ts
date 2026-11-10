export class ApiError extends Error {
  details?: unknown
  constructor(message: string, details?: unknown) {
    super(message)
    this.details = details
  }
}

export async function api<T>(path: string, options: { method?: string; body?: unknown; form?: FormData } = {}): Promise<T> {
  const headers: Record<string, string> = {}
  let body: BodyInit | undefined
  if (options.form) body = options.form
  else if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
    body = JSON.stringify(options.body)
  }
  const response = await fetch(`/api${path}`, {
    method: options.method || 'GET',
    credentials: 'include',
    headers,
    body,
  })
  if (response.status === 401 && !path.startsWith('/auth/login') && !path.startsWith('/auth/demo')) {
    window.dispatchEvent(new Event('hris-unauthorized'))
  }
  if (response.status === 204) return undefined as T
  const contentType = response.headers.get('content-type') || ''
  if (!contentType.includes('application/json')) {
    if (!response.ok) throw new ApiError('The server could not complete that request.')
    return response as T
  }
  const data = await response.json()
  if (!response.ok) throw new ApiError(data.error || 'Request failed', data.details)
  return data as T
}

export function useRows<T>(data: { items?: T[] } | T[] | null | undefined): T[] {
  if (!data) return []
  if (Array.isArray(data)) return data
  return data.items || []
}
