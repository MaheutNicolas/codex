import { api } from './client'

export const list = () => api.get('/api/books')
export const get = (id) => api.get(`/api/books/${id}`)
export const create = (name) => api.post('/api/books', { name })
export const rename = (id, name) => api.patch(`/api/books/${id}`, { name })
export const remove = (id) => api.delete(`/api/books/${id}`)
