import { locale } from '@/locales'

const formats = new Map()

/** "2026-10-07T22:28:55+02:00" -> "7 octobre 2026" or "October 7, 2026", in the language of the interface. */
export function formatDate(iso) {
  if (!formats.has(locale.value)) {
    formats.set(locale.value, new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long', year: 'numeric' }))
  }
  return formats.get(locale.value).format(new Date(iso))
}

/** Compares two names for sorting, according to the language of the interface. */
export const compareNames = (a, b) => a.localeCompare(b, locale.value)
