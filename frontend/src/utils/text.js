/** Lowercase, without accents: "Béatrice" -> "beatrice". Used to compare and search texts. */
export function normalize(text) {
  return text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
}

/** An identifier made from a name: "Citadelle du Nord" -> "citadelle-du-nord". */
export function slugify(text) {
  return normalize(text)
    .replace(/[^a-z0-9]+/g, '-')
    .slice(0, 100)
    .replace(/^-+|-+$/g, '')
}

// Same rule as the backend: lowercase letters and digits separated by single hyphens.
export const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/
