import { KNOWLEDGE_TYPES } from '@/constants'
import { t } from '@/locales'
import { SLUG_PATTERN } from '@/utils/text'

// The shape of an import document. The server only says "invalid JSON" when this shape is wrong
// and reports the first problem it meets while saving: everything else is checked here.

export const SECTIONS = ['knowledge', 'events', 'participants']

// The fields of each section with the value used when the document leaves them out.
const FIELDS = {
  knowledge: { id: undefined, type: undefined, name: undefined, summary: undefined, description: null, aliases: [] },
  events: {
    id: undefined,
    title: undefined,
    summary: undefined,
    detail: null,
    worldOrder: undefined,
    worldDate: null,
    chapter: null,
    revealed: true,
    tags: [],
  },
  participants: { eventId: undefined, knowledgeId: undefined, role: null },
}

export const fieldNames = (section) => Object.keys(FIELDS[section])

const MAX_ITEMS = 1000 // the same limit as the server
const INT_MAX = 2147483647

let nextUid = 1

/** The text an AI sent back, without the ```json fence it often wraps around the document. */
function stripFence(text) {
  const fenced = /^```[a-zA-Z]*\s*([\s\S]*?)\s*```$/.exec(text.trim())
  return fenced ? fenced[1] : text.trim()
}

/**
 * Reads the pasted text. Returns { error } when it cannot be used at all (a message for the user),
 * or { items } where each item is { uid, section, data, included }, to be checked and reviewed.
 */
export function parseDocument(text) {
  let document
  try {
    document = JSON.parse(stripFence(text))
  } catch (error) {
    return { error: t('import.parse.invalidJson', { reason: error.message }) }
  }

  if (document === null || typeof document !== 'object' || Array.isArray(document)) {
    return { error: t('import.parse.notObject') }
  }

  const items = []
  for (const [section, list] of Object.entries(document)) {
    if (!SECTIONS.includes(section)) return { error: t('import.parse.unknownSection', { section }) }
    if (!Array.isArray(list)) return { error: t('import.parse.notList', { section }) }
    if (list.length > MAX_ITEMS) return { error: t('import.parse.tooMany', { section, max: MAX_ITEMS }) }

    for (const [index, data] of list.entries()) {
      if (data === null || typeof data !== 'object' || Array.isArray(data)) {
        return { error: t('import.parse.notItem', { section, number: index + 1 }) }
      }
      items.push({ uid: nextUid++, section, data: { ...defaultsOf(section), ...data }, included: true })
    }
  }

  if (items.length === 0) return { error: t('import.parse.empty') }

  // Keep the document's order but group by section, so that the review reads knowledge, events, links.
  items.sort((a, b) => SECTIONS.indexOf(a.section) - SECTIONS.indexOf(b.section))

  return { items }
}

function defaultsOf(section) {
  return Object.fromEntries(
    Object.entries(FIELDS[section])
      .filter(([, value]) => value !== undefined)
      .map(([field, value]) => [field, Array.isArray(value) ? [] : value]),
  )
}

/** The key that tells two items of a section apart: an identifier, or the pair of a participant link. */
export const itemKey = (item) =>
  item.section === 'participants' ? `${item.data.eventId}|${item.data.knowledgeId}` : String(item.data.id)

const isString = (value) => typeof value === 'string'
const isStringList = (value) => Array.isArray(value) && value.every(isString)

/**
 * Checks one item. `context` holds what the item may refer to and what is already taken:
 *   availableKnowledge / availableEvents: Sets of the identifiers an participant link can point to
 *   duplicates: Set of the item keys that appear more than once among the items to import
 * Returns { field: message } (empty when the item is fine).
 */
export function validateItem(item, context) {
  const { section, data } = item
  const errors = {}
  const fail = (field, key, params = {}) => {
    errors[field] ??= t(`import.errors.${key}`, params)
  }

  for (const field of Object.keys(data)) {
    if (!(field in FIELDS[section])) fail(field, 'unknownField')
  }

  const required = (field, max) => {
    const value = data[field]
    if (value === undefined || value === null || value === '') return fail(field, 'required')
    if (!isString(value)) return fail(field, 'string')
    if (!value.trim()) return fail(field, 'required')
    if (value.length > max) fail(field, 'tooLong', { max })
  }
  const optionalText = (field, max) => {
    const value = data[field]
    if (value === null || value === undefined) return
    if (!isString(value)) return fail(field, 'string')
    if (value.length > max) fail(field, 'tooLong', { max })
  }
  const slug = (field) => {
    required(field, 100)
    if (!errors[field] && !SLUG_PATTERN.test(data[field])) fail(field, 'slug')
  }
  const list = (field) => {
    if (!isStringList(data[field])) fail(field, 'list')
  }

  if (section === 'knowledge') {
    slug('id')
    required('type', 50)
    if (!errors.type && !KNOWLEDGE_TYPES.some(({ value }) => value === data.type)) {
      fail('type', 'knowledgeType', { types: KNOWLEDGE_TYPES.map(({ value }) => value).join(', ') })
    }
    required('name', 255)
    required('summary', Infinity)
    optionalText('description', Infinity)
    list('aliases')
  } else if (section === 'events') {
    slug('id')
    required('title', 255)
    required('summary', Infinity)
    optionalText('detail', Infinity)
    if (!Number.isInteger(data.worldOrder) || Math.abs(data.worldOrder) > INT_MAX) fail('worldOrder', 'integer')
    optionalText('worldDate', 100)
    if (data.chapter !== null && data.chapter !== undefined) {
      if (!Number.isInteger(data.chapter) || data.chapter < 1 || data.chapter > INT_MAX) fail('chapter', 'chapter')
    }
    if (typeof data.revealed !== 'boolean') fail('revealed', 'boolean')
    list('tags')
  } else {
    required('eventId', 100)
    required('knowledgeId', 100)
    if (!errors.eventId && !context.availableEvents.has(data.eventId)) fail('eventId', 'unknownEvent')
    if (!errors.knowledgeId && !context.availableKnowledge.has(data.knowledgeId)) {
      fail('knowledgeId', 'unknownKnowledge')
    }
    optionalText('role', 50)
  }

  if (context.duplicates.has(`${section}:${itemKey(item)}`)) {
    fail(section === 'participants' ? 'knowledgeId' : 'id', 'duplicate')
  }

  return errors
}
