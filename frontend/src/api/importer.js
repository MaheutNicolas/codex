import { api } from './client'

/** Saves a document ({ knowledge, events, participants }) in one transaction: all of it or nothing. */
export const send = (bookId, document) => api.post(`/api/books/${bookId}/import`, document)
