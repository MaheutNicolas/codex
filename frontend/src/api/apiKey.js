import { api } from './client'

/** The key of a book, with its token: { bookId, token, scope, createdAt, lastUsedAt }. Created on first use. */
export const get = (bookId) => api.get(`/api/books/${bookId}/api-key`)

/** Gives the key a new token; the previous one stops working immediately. */
export const regenerate = (bookId) => api.post(`/api/books/${bookId}/api-key/regenerate`)
