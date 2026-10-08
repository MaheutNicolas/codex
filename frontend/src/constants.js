import {
  Baby,
  BadgeCheck,
  Crown,
  Dna,
  Flame,
  GraduationCap,
  Gem,
  Handshake,
  HandHelping,
  Heart,
  House,
  Lightbulb,
  Link,
  MapPin,
  Palette,
  Smile,
  Sparkles,
  Swords,
  Unlink,
  User,
  Users,
  Zap,
} from '@lucide/vue'

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

// Must match Relation::TYPES in the backend (same values, same order). A new type also needs its texts in
// locales/fr.js and locales/en.js (relations.types and relations.sentences) and an icon here.
// "none" ends the relation of a pair; the types that are not symmetric read differently from each side
// (the first entry is the mentor, the parent, the member, the leader or the servant of the second).
export const RELATION_TYPES = [
  { value: 'ally', icon: Handshake, symmetric: true },
  { value: 'enemy', icon: Swords, symmetric: true },
  { value: 'rival', icon: Flame, symmetric: true },
  { value: 'friend', icon: Smile, symmetric: true },
  { value: 'family', icon: House, symmetric: true },
  { value: 'partner', icon: Heart, symmetric: true },
  { value: 'mentor', icon: GraduationCap, symmetric: false },
  { value: 'parent', icon: Baby, symmetric: false },
  { value: 'member_of', icon: Users, symmetric: false },
  { value: 'leader_of', icon: Crown, symmetric: false },
  { value: 'serves', icon: HandHelping, symmetric: false },
  { value: 'other', icon: Link, symmetric: true },
  { value: 'none', icon: Unlink, symmetric: true },
]

export const relationIcon = (value) => RELATION_TYPES.find((type) => type.value === value)?.icon ?? Link
