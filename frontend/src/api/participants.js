import { api } from './client'

const link = (bookId, eventId, knowledgeId) =>
  `/api/books/${bookId}/event-participants/${encodeURIComponent(eventId)}/${encodeURIComponent(knowledgeId)}`

/** The links of one event: [{ eventId, knowledgeId, role }]. An event never has more than 200 participants in practice. */
export const listForEvent = (bookId, eventId) =>
  api
    .get(`/api/books/${bookId}/event-participants?eventId=${encodeURIComponent(eventId)}&limit=200`)
    .then((page) => page.data)

export const create = (bookId, data) => api.post(`/api/books/${bookId}/event-participants`, data)
export const update = (bookId, eventId, knowledgeId, role) =>
  api.patch(link(bookId, eventId, knowledgeId), { role })
export const remove = (bookId, eventId, knowledgeId) => api.delete(link(bookId, eventId, knowledgeId))
