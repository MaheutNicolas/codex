import fr from './fr'

/** Looks a text up by its dotted key ("books.title") and fills the {placeholders}. */
export function t(key, params = {}) {
  const value = key.split('.').reduce((node, part) => node?.[part], fr)
  if (typeof value !== 'string') return key

  return value.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? `{${name}}`)
}

/** Plural form: the key holds { one, other }. In French, 0 and 1 are singular. */
export function tn(key, count) {
  return t(`${key}.${count > 1 ? 'other' : 'one'}`, { count })
}

/** The French message for an ApiError, falling back to the message sent by the API. */
export function errorMessage(error) {
  return fr.errors[error?.code] ?? error?.message ?? fr.errors.INTERNAL_ERROR
}
