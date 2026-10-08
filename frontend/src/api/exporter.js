import { api } from './client'

/** Everything a book holds, in one call: { knowledge, events, participants } (the shape of an import document). */
export const get = (bookId) => api.get(`/api/books/${bookId}/export`)
