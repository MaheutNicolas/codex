import { KNOWLEDGE_TYPES } from '@/constants'
import { t } from '@/locales'
import { slugify } from '@/utils/text'

// Builds the files of an export from the document sent by GET /api/books/{bookId}/export.

/** Without the secret events (the reader does not know them yet) and the links that point to them. */
export function withoutSecrets(document) {
  const events = document.events.filter((event) => event.revealed)
  const kept = new Set(events.map((event) => event.id))

  return {
    ...document,
    events,
    participants: document.participants.filter((link) => kept.has(link.eventId)),
    relations: (document.relations ?? []).filter((relation) => relation.revealed),
  }
}

/** Our own format: the same document the import accepts, so that an export can be imported again. */
export const toJson = (document) => JSON.stringify(document, null, 2)

/** The plural name of a type of entry, or the type itself when it has no text yet. */
function typeLabel(type) {
  const key = `library.typesPlural.${type}`
  const label = t(key)
  return label === key ? type : label
}

/** A readable document: the library by type, then the timeline in the order of the world. */
export function toMarkdown(document, bookName) {
  const names = new Map(document.knowledge.map((entry) => [entry.id, entry.name]))
  const links = new Map()
  for (const link of document.participants) {
    if (!links.has(link.eventId)) links.set(link.eventId, [])
    const name = names.get(link.knowledgeId) ?? link.knowledgeId
    links.get(link.eventId).push(link.role ? `${name} (${link.role})` : name)
  }

  const lines = [`# ${bookName}`, '']

  lines.push(`## ${t('export.md.library')}`, '')
  const types = [...new Set([...KNOWLEDGE_TYPES.map(({ value }) => value), ...document.knowledge.map((entry) => entry.type)])]
  for (const type of types) {
    const entries = document.knowledge.filter((entry) => entry.type === type)
    if (entries.length === 0) continue

    lines.push(`### ${typeLabel(type)}`, '')
    for (const entry of entries) {
      lines.push(`#### ${entry.name}`, '', `*${t('export.md.id', { id: entry.id })}*`, '')
      if (entry.aliases.length) lines.push(`**${t('library.aliases', { list: entry.aliases.join(', ') })}**`, '')
      lines.push(entry.summary, '')
      if (entry.description) lines.push(entry.description, '')
    }
  }

  lines.push(`## ${t('export.md.timeline')}`, '')
  for (const event of document.events) {
    const facts = [
      t('timeline.order', { number: event.worldOrder }),
      event.worldDate,
      event.chapter === null ? t('timeline.offscreen') : t('timeline.chapter', { number: event.chapter }),
      event.revealed ? null : t('timeline.secret'),
    ].filter(Boolean)

    lines.push(`### ${event.title}`, '', `*${facts.join(' · ')} · ${t('export.md.id', { id: event.id })}*`, '', event.summary, '')
    if (event.detail) lines.push(event.detail, '')
    if (links.has(event.id)) lines.push(`**${t('export.md.participants')}** ${links.get(event.id).join(', ')}`, '')
    if (event.tags.length) lines.push(`**${t('export.md.tags')}** ${event.tags.join(', ')}`, '')
  }

  return `${lines.join('\n').trimEnd()}\n`
}

/** "My Book" + "json" -> "my-book-2026-10-08.json" */
export function fileName(bookName, extension) {
  return `${slugify(bookName) || 'codex'}-${new Date().toISOString().slice(0, 10)}.${extension}`
}

/** Hands a text to the browser as a file to save. */
export function download(name, text, mime) {
  const url = URL.createObjectURL(new Blob([text], { type: `${mime};charset=utf-8` }))
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}
