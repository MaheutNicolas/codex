const dateFormat = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

/** "2026-10-07T22:28:55+02:00" -> "7 octobre 2026" */
export function formatDate(iso) {
  return dateFormat.format(new Date(iso))
}
