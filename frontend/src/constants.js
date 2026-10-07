import { MapPin, Sparkles, User } from '@lucide/vue'

// Must match Knowledge::TYPES in the backend. A new type also needs its texts in
// locales/fr.js (library.types and library.typesPlural) and an icon here.
export const KNOWLEDGE_TYPES = [
  { value: 'character', icon: User },
  { value: 'place', icon: MapPin },
  { value: 'system', icon: Sparkles },
]

export const typeIcon = (value) => KNOWLEDGE_TYPES.find((type) => type.value === value)?.icon ?? User
