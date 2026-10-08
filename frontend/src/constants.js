import { BadgeCheck, Dna, Gem, Lightbulb, MapPin, Palette, Sparkles, User, Users, Zap } from '@lucide/vue'

// Must match Knowledge::TYPES in the backend (same values, same order). A new type also needs its texts in
// locales/fr.js and locales/en.js (library.types, library.typesPlural, import.instructions.text) and an icon here.
export const KNOWLEDGE_TYPES = [
  { value: 'character', icon: User },
  { value: 'group', icon: Users },
  { value: 'species', icon: Dna },
  { value: 'place', icon: MapPin },
  { value: 'item', icon: Gem },
  { value: 'system', icon: Sparkles },
  { value: 'ability', icon: Zap },
  { value: 'concept', icon: Lightbulb },
  { value: 'rank', icon: BadgeCheck },
  { value: 'theme', icon: Palette },
]

export const typeIcon = (value) => KNOWLEDGE_TYPES.find((type) => type.value === value)?.icon ?? User
