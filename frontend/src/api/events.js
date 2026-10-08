import { api } from './client'

const PAGE_SIZE = 200 // the maximum the API accepts

/** Every event of a book in chronological order (short fields only), whatever the number of pages. */
export async function listAll(bookId) {
  const events = []
  let total = Infinity

  while (events.length < total) {
    const page = await api.get(`/api/books/${bookId}/events?limit=${PAGE_SIZE}&offset=${events.length}`)
    events.push(...page.data)
    total = page.total
    if (page.data.length === 0) break
  }

  return events
}

export const get = (bookId, id) => api.get(`/api/books/${bookId}/events/${encodeURIComponent(id)}`)
export const create = (bookId, data) => api.post(`/api/books/${bookId}/events`, data)
export const update = (bookId, id, data) => api.patch(`/api/books/${bookId}/events/${encodeURIComponent(id)}`, data)
export const remove = (bookId, id) => api.delete(`/api/books/${bookId}/events/${encodeURIComponent(id)}`)
