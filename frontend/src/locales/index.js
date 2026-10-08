import { ref, watchEffect } from 'vue'
import en from './en'
import fr from './fr'

const STORAGE_KEY = 'codex.locale'

// A language needs its messages here, its plural rule below and its tag for dates and sorting.
const MESSAGES = { fr, en }
export const LOCALES = Object.keys(MESSAGES)

function detect() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (LOCALES.includes(saved)) return saved
  } catch {
    // Private browsing: fall back to the browser language.
  }
  return navigator.language?.toLowerCase().startsWith('fr') ? 'fr' : 'en'
}

/** The language of the interface. Reading it inside a template or a computed makes that part follow a change. */
export const locale = ref(detect())

export function setLocale(value) {
  if (!LOCALES.includes(value)) return
  locale.value = value
  try {
    localStorage.setItem(STORAGE_KEY, value)
  } catch {
    // The choice just lasts until the page is closed.
  }
}

// The browser needs to know the language of the page (spell checking, hyphenation, screen readers).
watchEffect(() => {
  document.documentElement.lang = locale.value
})

/** Looks a text up by its dotted key ("books.title") and fills the {placeholders}. */
export function t(key, params = {}) {
  const value = key.split('.').reduce((node, part) => node?.[part], MESSAGES[locale.value])
  if (typeof value !== 'string') return key

  return value.replace(/\{(\w+)\}/g, (_, name) => params[name] ?? `{${name}}`)
}

/** Plural form: the key holds { one, other }. In French 0 and 1 are singular, in English only 1 is. */
export function tn(key, count) {
  const singular = locale.value === 'fr' ? count <= 1 : count === 1
  return t(`${key}.${singular ? 'one' : 'other'}`, { count })
}

/** The message for an ApiError, falling back to the message sent by the API. */
export function errorMessage(error) {
  const messages = MESSAGES[locale.value].errors
  return messages[error?.code] ?? error?.message ?? messages.INTERNAL_ERROR
}
