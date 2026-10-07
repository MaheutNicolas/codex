/** An error answered by the API ({ error: { code, message, details } }), or a network failure. */
export class ApiError extends Error {
  constructor(status, code, message, details = {}) {
    super(message)
    this.status = status
    this.code = code
    this.details = details
  }

  /** The error messages of one field, from a VALIDATION_FAILED answer. */
  fieldErrors(field) {
    return this.details?.fields?.[field] ?? []
  }
}

let onUnauthorized = () => {}

/** Called when a request that needed a session gets UNAUTHORIZED (expired session). */
export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

async function request(method, path, body) {
  let response
  try {
    response = await fetch(path, {
      method,
      credentials: 'same-origin',
      headers: body === undefined ? {} : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'NETWORK_ERROR', 'The server cannot be reached.')
  }

  if (response.status === 204) return null

  const data = await response.json().catch(() => null)
  if (response.ok) return data

  const error = data?.error
  const apiError = new ApiError(
    response.status,
    error?.code ?? 'INTERNAL_ERROR',
    error?.message ?? response.statusText,
    error?.details ?? {},
  )
  if (apiError.code === 'UNAUTHORIZED') onUnauthorized(apiError)

  throw apiError
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, body = {}) => request('POST', path, body),
  patch: (path, body) => request('PATCH', path, body),
  delete: (path) => request('DELETE', path),
}
