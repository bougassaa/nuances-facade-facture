export type ApiError = { error: string }

let csrfToken = ''

export function setCsrfToken(token: string) {
  csrfToken = token
}

export function getCsrfToken() {
  return csrfToken
}

async function parseJson(res: Response) {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return { error: text }
  }
}

export async function api<T = unknown>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const headers = new Headers(options.headers)
  const method = (options.method ?? 'GET').toUpperCase()
  const isForm = typeof FormData !== 'undefined' && options.body instanceof FormData

  if (!isForm && !headers.has('Content-Type') && options.body) {
    headers.set('Content-Type', 'application/json')
  }
  if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
    headers.set('X-CSRF-Token', csrfToken)
  }

  const res = await fetch(`/api${path}`, {
    ...options,
    headers,
    credentials: 'include',
  })

  if (res.status === 204) {
    return undefined as T
  }

  const data = await parseJson(res)

  if (data && typeof data === 'object' && 'csrf_token' in data && typeof (data as { csrf_token: string }).csrf_token === 'string') {
    setCsrfToken((data as { csrf_token: string }).csrf_token)
  }

  if (!res.ok) {
    const message =
      data && typeof data === 'object' && 'error' in data
        ? String((data as ApiError).error)
        : `Erreur ${res.status}`
    throw new Error(message)
  }

  return data as T
}

export async function downloadPdf(documentId: number, filenameHint?: string) {
  const res = await fetch(`/api/documents/${documentId}/pdf`, {
    credentials: 'include',
  })
  if (!res.ok) {
    const data = await parseJson(res)
    throw new Error(
      data && typeof data === 'object' && 'error' in data
        ? String((data as ApiError).error)
        : 'PDF impossible',
    )
  }
  const blob = await res.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filenameHint ? `${filenameHint}.pdf` : `document-${documentId}.pdf`
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
