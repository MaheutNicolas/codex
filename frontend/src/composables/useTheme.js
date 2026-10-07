import { reactive } from 'vue'

const STORAGE_KEY = 'codex.theme'

// The accents must match the [data-accent] presets of src/styles/_theme.scss.
export const MODES = ['system', 'light', 'dark']
export const ACCENTS = ['indigo', 'teal', 'green', 'amber', 'rose']

function load() {
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}')
    return {
      mode: MODES.includes(saved.mode) ? saved.mode : 'system',
      accent: ACCENTS.includes(saved.accent) ? saved.accent : 'indigo',
    }
  } catch {
    return { mode: 'system', accent: 'indigo' }
  }
}

const state = reactive(load())

/** Writes the choices on <html>, where the theme stylesheet reads them. */
function apply() {
  const root = document.documentElement
  // "system" means: no attribute, so the prefers-color-scheme media query decides.
  if (state.mode === 'system') delete root.dataset.theme
  else root.dataset.theme = state.mode
  root.dataset.accent = state.accent
}

function persist() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ mode: state.mode, accent: state.accent }))
  } catch {
    // Private browsing: the choice just lasts until the page is closed.
  }
}

export function useTheme() {
  return {
    state,
    apply,
    setMode(mode) {
      state.mode = mode
      apply()
      persist()
    },
    setAccent(accent) {
      state.accent = accent
      apply()
      persist()
    },
  }
}
