import { api } from './client'

const PAGE_SIZE = 200 // the maximum the API accepts

/** Every state of every relation of a book, by chapter (the start of the book first), whatever the number of pages. */
export async function listAll(bookId) {
  const relations = []
  let total = Infinity

  while (relations.length < total) {
    const page = await api.get(`/api/books/${bookId}/relations?limit=${PAGE_SIZE}&offset=${relations.length}`)
    relations.push(...page.data)
    total = page.total
    if (page.data.length === 0) break
  }

  return relations
}

export const get = (bookId, id) => api.get(`/api/books/${bookId}/relations/${encodeURIComponent(id)}`)
export const create = (bookId, data) => api.post(`/api/books/${bookId}/relations`, data)
export const update = (bookId, id, data) => api.patch(`/api/books/${bookId}/relations/${encodeURIComponent(id)}`, data)
export const remove = (bookId, id) => api.delete(`/api/books/${bookId}/relations/${encodeURIComponent(id)}`)
