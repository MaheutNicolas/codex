<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ArrowLeft, Check, ClipboardCopy, Upload } from '@lucide/vue'
import ImportItemPanel from '@/components/import/ImportItemPanel.vue'
import ImportReview from '@/components/import/ImportReview.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import * as eventsApi from '@/api/events'
import * as importer from '@/api/importer'
import * as knowledgeApi from '@/api/knowledge'
import { KNOWLEDGE_TYPES } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { itemKey, parseDocument, SECTIONS, validateItem } from '@/utils/importDocument'

const route = useRoute()
const toast = useToast()

const bookId = computed(() => route.params.bookId)

// ---- What the book already holds: the AI must not duplicate it, and links may point to it ---------

const existingKnowledge = ref(new Map()) // id -> { name, type }
const existingEvents = ref(new Map()) // id -> { title, worldOrder, chapter }
const loading = ref(true)

async function loadExisting() {
  loading.value = true
  try {
    const [entries, events] = await Promise.all([knowledgeApi.lexicon(bookId.value), eventsApi.listAll(bookId.value)])
    existingKnowledge.value = new Map(entries.map((entry) => [entry.id, entry]))
    existingEvents.value = new Map(events.map((event) => [event.id, event]))
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

// ---- Step 1: the instructions for the AI, and the pasted answer -----------------------------------

const step = ref('input') // 'input' | 'review' | 'done'
const text = ref('')
const parseError = ref('')

const instructions = computed(() => {
  const knowledge = [...existingKnowledge.value.values()]
    .map((entry) => `- ${entry.id} : ${entry.name} (${t(`library.types.${entry.type}`)})`)
    .join('\n')
  const events = [...existingEvents.value.values()]
    .map((event) => `- ${event.id} : ${event.title} (${t('import.instructions.orderOf', { number: event.worldOrder })})`)
    .join('\n')
  const lastOrder = Math.max(0, ...[...existingEvents.value.values()].map((event) => event.worldOrder))

  return t('import.instructions.text', {
    types: KNOWLEDGE_TYPES.map(({ value }) => value).join(', '),
    knowledge: knowledge || t('import.instructions.none'),
    events: events || t('import.instructions.none'),
    nextOrder: lastOrder + 1,
  })
})

async function copyInstructions() {
  try {
    await navigator.clipboard.writeText(instructions.value)
    toast.success(t('import.instructions.copied'))
  } catch {
    toast.error(t('keys.copyFailed'))
  }
}

function analyse() {
  const result = parseDocument(text.value)
  parseError.value = result.error ?? ''
  if (result.error) return

  // What the book already holds is ignored by default: the AI was told not to resend it, but it may.
  items.value = result.items.map((item) => ({ ...item, included: !existsInBook(item) }))
  touched.value = false
  serverError.value = null
  step.value = 'review'
}

// ---- Step 2: the review ------------------------------------------------------------------------------

const items = ref([])
const touched = ref(false) // true once the user changed something that going back would lose
const editingUid = ref(null)
const backOpen = ref(false)

const existsInBook = (item) => {
  if (item.section === 'knowledge') return existingKnowledge.value.has(item.data.id)
  if (item.section === 'events') return existingEvents.value.has(item.data.id)
  return false
}

/** What an item may point to and what is taken, given a list of items (the checks need the whole list). */
function contextFor(list) {
  const included = list.filter((item) => item.included)
  const idsOf = (section, existing) =>
    new Set([
      ...existing.keys(),
      ...included
        .filter((item) => item.section === section && typeof item.data.id === 'string')
        .map((item) => item.data.id),
    ])

  const counts = new Map()
  for (const item of included) {
    const key = `${item.section}:${itemKey(item)}`
    counts.set(key, (counts.get(key) ?? 0) + 1)
  }

  return {
    availableKnowledge: idsOf('knowledge', existingKnowledge.value),
    availableEvents: idsOf('events', existingEvents.value),
    duplicates: new Set([...counts].filter(([, count]) => count > 1).map(([key]) => key)),
  }
}

const context = computed(() => contextFor(items.value))

const nameOfKnowledge = (id) =>
  items.value.find((item) => item.section === 'knowledge' && item.included && item.data.id === id)?.data.name ??
  existingKnowledge.value.get(id)?.name ??
  id
const titleOfEvent = (id) =>
  items.value.find((item) => item.section === 'events' && item.included && item.data.id === id)?.data.title ??
  existingEvents.value.get(id)?.title ??
  id

function statusOf(item) {
  if (item.section === 'participants') return item.included ? '' : t('import.status.ignored')
  const exists = existsInBook(item)
  if (item.included) return t(exists ? 'import.status.update' : 'import.status.new')
  return t(exists ? 'import.status.existsIgnored' : 'import.status.ignored')
}

function rowOf(item) {
  const { data } = item
  const shown = (value) => (typeof value === 'string' && value ? value : '')

  if (item.section === 'knowledge') {
    return { title: shown(data.name) || shown(data.id) || '?', ids: shown(data.id), detail: shown(data.summary) }
  }
  if (item.section === 'events') {
    const place = [
      Number.isInteger(data.worldOrder) ? t('timeline.order', { number: data.worldOrder }) : '',
      Number.isInteger(data.chapter) ? t('timeline.chapter', { number: data.chapter }) : '',
    ]
    return {
      title: shown(data.title) || shown(data.id) || '?',
      ids: shown(data.id),
      detail: [...place, shown(data.summary)].filter(Boolean).join(' · '),
    }
  }
  return {
    title: `${titleOfEvent(data.eventId)} · ${nameOfKnowledge(data.knowledgeId)}`,
    ids: '',
    detail: shown(data.role) ? t('import.review.roleIs', { role: data.role }) : '',
  }
}

const rows = computed(() =>
  items.value.map((item) => ({
    item,
    ...rowOf(item),
    status: statusOf(item),
    errors: item.included ? validateItem(item, context.value) : {},
  })),
)

const summary = computed(() => {
  const included = rows.value.filter((row) => row.item.included)
  const knowledgeOrEvent = included.filter((row) => row.item.section !== 'participants')
  return {
    created: knowledgeOrEvent.filter((row) => !existsInBook(row.item)).length,
    updated: knowledgeOrEvent.filter((row) => existsInBook(row.item)).length,
    links: included.length - knowledgeOrEvent.length,
    ignored: rows.value.length - included.length,
    errors: included.filter((row) => Object.keys(row.errors).length > 0).length,
    total: included.length,
  }
})

function toggle(uid) {
  const item = items.value.find((entry) => entry.uid === uid)
  item.included = !item.included
  touched.value = true
}

function remove(uid) {
  items.value = items.value.filter((entry) => entry.uid !== uid)
  touched.value = true
  if (items.value.length === 0) back(true)
}

const editing = computed(() => items.value.find((item) => item.uid === editingUid.value) ?? null)

function applyEdit(data) {
  editing.value.data = data
  touched.value = true
  editingUid.value = null
}

// The panel checks a corrected item as if it replaced the original in the list.
const checkCandidate = (candidate) =>
  validateItem(candidate, contextFor(items.value.map((item) => (item.uid === candidate.uid ? candidate : item))))

function back(force = false) {
  if (touched.value && !force) {
    backOpen.value = true
    return
  }
  backOpen.value = false
  step.value = 'input'
}

// ---- Step 3: saving ------------------------------------------------------------------------------------

const sending = ref(false)
const serverError = ref(null) // { message, lines: [string], uid }
const focusUid = ref(null)
const result = ref(null)

async function submit() {
  const document = Object.fromEntries(SECTIONS.map((section) => [section, []]))
  const sent = Object.fromEntries(SECTIONS.map((section) => [section, []])) // the items, in the order they are sent
  for (const item of items.value.filter((entry) => entry.included)) {
    document[item.section].push(item.data)
    sent[item.section].push(item)
  }

  sending.value = true
  serverError.value = null
  focusUid.value = null
  try {
    result.value = await importer.send(bookId.value, document)
    step.value = 'done'
    await loadExisting()
  } catch (error) {
    showServerError(error, sent)
  } finally {
    sending.value = false
  }
}

/** Says why nothing was saved, and which item stopped the import when the server names it. */
function showServerError(error, sent) {
  const lines = []
  const [, section, index] = /^(\w+)\[(\d+)]$/.exec(error.details?.path ?? '') ?? []
  const culprit = section ? sent[section]?.[Number(index)] : null
  if (culprit) {
    const { title } = rowOf(culprit)
    lines.push(t('import.server.item', { section: t(`import.sections.${section}`), title }))
  }

  if (error.code === 'INVALID_JSON') lines.push(t('import.server.invalidJson'), error.message)
  for (const [field, messages] of Object.entries(error.details?.fields ?? {})) {
    lines.push(`${field} : ${messages.join(' ')}`)
  }
  if (error.details?.field) lines.push(t('import.server.reference', { field: error.details.field, id: error.details.id }))
  if (error.code === 'ID_ALREADY_EXISTS' && error.details?.id) lines.push(error.details.id)

  serverError.value = { message: errorMessage(error), lines }
  focusUid.value = culprit?.uid ?? null
}

function reset() {
  step.value = 'input'
  text.value = ''
  parseError.value = ''
  items.value = []
  touched.value = false
  serverError.value = null
  focusUid.value = null
  result.value = null
  editingUid.value = null
}
// Started last: it resets the state declared above.
watch(
  bookId,
  () => {
    reset()
    loadExisting()
  },
  { immediate: true },
)
</script>

<template>
  <div class="page import">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('import.title') }}</h1>
        <p class="page__subtitle">{{ t('import.subtitle') }}</p>
      </div>
    </header>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <!-- Step 1 -->
    <template v-else-if="step === 'input'">
      <section class="c-card import__card" aria-labelledby="import-step-1">
        <h2 id="import-step-1" class="c-card__title">{{ t('import.step1.title') }}</h2>
        <p class="u-muted">{{ t('import.step1.text') }}</p>
        <pre class="import__instructions" tabindex="0">{{ instructions }}</pre>
        <div>
          <UiButton @click="copyInstructions">
            <ClipboardCopy aria-hidden="true" />
            {{ t('keys.copy') }}
          </UiButton>
        </div>
      </section>

      <section class="c-card import__card" aria-labelledby="import-step-2">
        <h2 id="import-step-2" class="c-card__title">{{ t('import.step2.title') }}</h2>
        <p class="u-muted">{{ t('import.step2.text') }}</p>
        <UiField
          v-model="text"
          multiline
          :rows="12"
          class="import__paste"
          :label="t('import.step2.label')"
          :placeholder="t('import.step2.placeholder')"
          :error="parseError"
          @update:model-value="parseError = ''"
        />
        <div>
          <UiButton variant="primary" :disabled="!text.trim()" @click="analyse">
            <Upload aria-hidden="true" />
            {{ t('import.step2.analyse') }}
          </UiButton>
        </div>
      </section>
    </template>

    <!-- Step 2 -->
    <template v-else-if="step === 'review'">
      <div class="import__bar">
        <UiButton variant="ghost" @click="back()">
          <ArrowLeft aria-hidden="true" />
          {{ t('import.review.back') }}
        </UiButton>
        <p class="import__summary">
          {{ t('import.review.summary', summary) }}
          <span v-if="summary.errors" class="import__summary-errors">
            {{ t('import.review.errors', { count: summary.errors }) }}
          </span>
        </p>
        <UiButton variant="primary" :loading="sending" :disabled="summary.total === 0 || summary.errors > 0" @click="submit">
          <Check aria-hidden="true" />
          {{ t('import.review.submit') }}
        </UiButton>
      </div>

      <div v-if="serverError" class="import__alert" role="alert">
        <p class="import__alert-title">{{ t('import.server.title') }} {{ serverError.message }}</p>
        <ul v-if="serverError.lines.length">
          <li v-for="line in serverError.lines" :key="line">{{ line }}</li>
        </ul>
        <p class="u-muted">{{ t('import.server.nothingSaved') }}</p>
      </div>

      <p class="u-muted import__help">{{ t('import.review.help') }}</p>

      <ImportReview
        :rows="rows"
        :focus-uid="focusUid"
        @toggle="toggle"
        @edit="editingUid = $event"
        @remove="remove"
      />

      <ImportItemPanel :item="editing" :check="checkCandidate" @close="editingUid = null" @apply="applyEdit" />

      <UiDialog :open="backOpen" :title="t('import.review.backTitle')" @dismiss="backOpen = false">
        <p>{{ t('import.review.backText') }}</p>
        <template #footer>
          <UiButton autofocus @click="backOpen = false">{{ t('library.keepEditing') }}</UiButton>
          <UiButton variant="danger" @click="back(true)">{{ t('library.discard') }}</UiButton>
        </template>
      </UiDialog>
    </template>

    <!-- Done -->
    <div v-else class="c-empty">
      <span class="c-empty__icon"><Check aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('import.done.title') }}</h2>
      <p>
        {{
          t('import.done.text', {
            created: result.created.knowledge + result.created.events + result.created.participants,
            updated: result.updated.knowledge + result.updated.events + result.updated.participants,
          })
        }}
      </p>
      <div class="import__done-actions">
        <RouterLink class="c-button c-button--secondary" :to="{ name: 'library', params: { bookId } }">
          {{ t('nav.library') }}
        </RouterLink>
        <RouterLink class="c-button c-button--secondary" :to="{ name: 'timeline', params: { bookId } }">
          {{ t('nav.timeline') }}
        </RouterLink>
        <UiButton variant="primary" @click="reset">{{ t('import.done.again') }}</UiButton>
      </div>
    </div>
  </div>
</template>
