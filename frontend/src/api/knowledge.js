import { api } from './client'

const PAGE_SIZE = 200 // the maximum the API accepts

/** Every knowledge entry of a book (short fields only), whatever the number of pages. */
export async function listAll(bookId) {
  const entries = []
  let total = Infinity

  while (entries.length < total) {
    const page = await api.get(`/api/books/${bookId}/knowledge?limit=${PAGE_SIZE}&offset=${entries.length}`)
    entries.push(...page.data)
    total = page.total
    if (page.data.length === 0) break
  }

  return entries
}

export const get = (bookId, id) => api.get(`/api/books/${bookId}/knowledge/${encodeURIComponent(id)}`)
export const create = (bookId, data) => api.post(`/api/books/${bookId}/knowledge`, data)
export const update = (bookId, id, data) => api.patch(`/api/books/${bookId}/knowledge/${encodeURIComponent(id)}`, data)
export const remove = (bookId, id) => api.delete(`/api/books/${bookId}/knowledge/${encodeURIComponent(id)}`)
