<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowDown, ArrowUp, CalendarClock, EyeOff, Plus, Search } from '@lucide/vue'
import EventPanel from '@/components/timeline/EventPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import * as eventsApi from '@/api/events'
import * as knowledgeApi from '@/api/knowledge'
import { useToast } from '@/composables/useToast'
import { errorMessage, t, tn } from '@/locales'
import { normalize } from '@/utils/text'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = computed(() => route.params.bookId)
const events = ref([]) // in chronological order, like the server sends them
const lexicon = ref([]) // the knowledge entries that can take part in an event
const loading = ref(true)
const moving = ref(false)
const query = ref('')
const chapterFilter = ref('all') // 'all', 'none' (off-screen) or a chapter number
const visibility = ref('all') // 'all', 'revealed' or 'secret'
const searchInput = ref(null)

async function load() {
  loading.value = true
  try {
    const [list, entries] = await Promise.all([eventsApi.listAll(bookId.value), knowledgeApi.lexicon(bookId.value)])
    events.value = list
    lexicon.value = entries
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

watch(
  bookId,
  () => {
    resetFilters()
    load()
  },
  { immediate: true },
)

// ---- Search and filters -------------------------------------------------------------------

const chapterOptions = computed(() => {
  const chapters = [...new Set(events.value.map((event) => event.chapter).filter((chapter) => chapter !== null))]
  chapters.sort((a, b) => a - b)

  return [
    { value: 'all', label: t('timeline.allChapters') },
    ...chapters.map((chapter) => ({ value: String(chapter), label: t('timeline.chapter', { number: chapter }) })),
    ...(events.value.some((event) => event.chapter === null)
      ? [{ value: 'none', label: t('timeline.offscreenFilter') }]
      : []),
  ]
})

const filtering = computed(
  () => query.value.trim() !== '' || chapterFilter.value !== 'all' || visibility.value !== 'all',
)

const visible = computed(() => {
  const search = normalize(query.value.trim())

  return events.value.filter((event) => {
    if (chapterFilter.value === 'none' && event.chapter !== null) return false
    if (chapterFilter.value !== 'all' && chapterFilter.value !== 'none' && String(event.chapter) !== chapterFilter.value) {
      return false
    }
    if (visibility.value === 'revealed' && !event.revealed) return false
    if (visibility.value === 'secret' && event.revealed) return false
    if (!search) return true

    return [event.title, event.summary, event.id, event.worldDate ?? ''].some((text) => normalize(text).includes(search))
  })
})

function resetFilters() {
  query.value = ''
  chapterFilter.value = 'all'
  visibility.value = 'all'
}

// ---- Reordering: the world order of an event is a plain integer ---------------------------------

// Moving an event swaps its world order with its neighbour's (two requests). Two events sharing the
// same order cannot be told apart by a swap, so then the orders that no longer increase strictly are rewritten.
async function move(event, direction) {
  const from = events.value.findIndex((item) => item.id === event.id)
  const to = from + direction
  if (moving.value || to < 0 || to >= events.value.length) return

  const list = events.value.map((item) => ({ ...item }))
  const changed = []

  if (list[from].worldOrder !== list[to].worldOrder) {
    ;[list[from].worldOrder, list[to].worldOrder] = [list[to].worldOrder, list[from].worldOrder]
    ;[list[from], list[to]] = [list[to], list[from]]
    changed.push(list[from], list[to])
  } else {
    ;[list[from], list[to]] = [list[to], list[from]]
    for (let index = Math.min(from, to); index < list.length; index++) {
      const previous = list[index - 1]
      if (previous && list[index].worldOrder <= previous.worldOrder) {
        list[index].worldOrder = previous.worldOrder + 1
        changed.push(list[index])
      } else if (index > Math.max(from, to)) {
        break
      }
    }
  }

  moving.value = true
  events.value = list
  try {
    for (const item of changed) await eventsApi.update(bookId.value, item.id, { worldOrder: item.worldOrder })
  } catch (error) {
    toast.error(`${t('timeline.reorderFailed')} ${errorMessage(error)}`)
    await load()
  } finally {
    moving.value = false
  }
}

// ---- The edit panel is driven by the URL: ?event=evt-0042 edits, ?new creates --------------------

const eventParam = computed(() => (typeof route.query.event === 'string' ? route.query.event : null))
const creating = computed(() => route.query.new !== undefined)
const panelOpen = computed(() => creating.value || eventParam.value !== null)

// Suggestions for a new event: the next evt-NNNN identifier and the order after the last event.
const nextId = computed(() => {
  const numbers = events.value.map((event) => /^evt-(\d+)$/.exec(event.id)?.[1]).filter(Boolean).map(Number)
  return `evt-${String((numbers.length ? Math.max(...numbers) : 0) + 1).padStart(4, '0')}`
})
const nextOrder = computed(() => (events.value.length ? events.value[events.value.length - 1].worldOrder + 1 : 1))

const openEvent = (id) => router.replace({ query: { event: id } })
const openCreate = () => router.replace({ query: { new: '1' } })
const closePanel = () => router.replace({ query: {} })

// The list follows what the panel does, without asking the server again.
function onSaved(event) {
  const row = {
    id: event.id,
    title: event.title,
    summary: event.summary,
    worldOrder: event.worldOrder,
    worldDate: event.worldDate,
    chapter: event.chapter,
    revealed: event.revealed,
  }
  const index = events.value.findIndex((existing) => existing.id === row.id)
  if (index >= 0) events.value[index] = row
  else events.value.push(row)
  events.value.sort((a, b) => a.worldOrder - b.worldOrder || (a.id < b.id ? -1 : 1))
}

function onDeleted(id) {
  events.value = events.value.filter((event) => event.id !== id)
}

// ---- Keyboard: "/" or Ctrl+K jumps to the search ----------------------------------------------

function onKeydown(event) {
  if (panelOpen.value) return
  const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable
  const slash = event.key === '/' && !typing && !event.ctrlKey && !event.metaKey
  const ctrlK = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k'
  if (slash || ctrlK) {
    event.preventDefault()
    searchInput.value?.focus()
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="page timeline">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('timeline.title') }}</h1>
        <p class="page__subtitle">{{ t('timeline.subtitle') }}</p>
      </div>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('timeline.new') }}
      </UiButton>
    </header>

    <div class="library__toolbar">
      <div class="c-search">
        <Search class="c-search__icon" aria-hidden="true" />
        <input
          ref="searchInput"
          v-model="query"
          class="c-field__input c-search__input"
          type="search"
          :placeholder="t('timeline.search')"
          :aria-label="t('timeline.searchLabel')"
          autocomplete="off"
        />
        <kbd v-if="!query" class="c-search__hint" aria-hidden="true">/</kbd>
      </div>

      <div class="library__filters" role="group">
        <button
          v-for="option in ['all', 'revealed', 'secret']"
          :key="option"
          type="button"
          class="c-chip"
          :aria-pressed="visibility === option"
          @click="visibility = option"
        >
          {{ t(`timeline.visibility.${option}`) }}
        </button>
      </div>

      <UiSelect v-model="chapterFilter" class="timeline__chapter" :label="t('timeline.chapterLabel')" :options="chapterOptions" />
    </div>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <div v-else-if="events.length === 0" class="c-empty">
      <span class="c-empty__icon"><CalendarClock aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('timeline.empty.title') }}</h2>
      <p>{{ t('timeline.empty.text') }}</p>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('timeline.new') }}
      </UiButton>
    </div>

    <div v-else-if="visible.length === 0" class="c-empty">
      <span class="c-empty__icon"><Search aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('timeline.noResults.title') }}</h2>
      <p>{{ t('timeline.noResults.text') }}</p>
      <UiButton @click="resetFilters">{{ t('timeline.noResults.reset') }}</UiButton>
    </div>

    <template v-else>
      <p class="library__count">{{ tn('timeline.count', visible.length) }}</p>
      <ol class="timeline__list">
        <li
          v-for="(event, index) in visible"
          :key="event.id"
          class="timeline__item"
          :class="{ 'timeline__item--new-chapter': index > 0 && visible[index - 1].chapter !== event.chapter }"
        >
          <button
            type="button"
            class="timeline__row"
            :class="{ 'is-active': event.id === eventParam }"
            @click="openEvent(event.id)"
          >
            <span class="timeline__order" :title="t('timeline.order', { number: event.worldOrder })">
              {{ event.worldOrder }}
            </span>
            <span class="timeline__body">
              <span class="timeline__title-line">
                <span class="timeline__title">{{ event.title }}</span>
                <span v-if="event.worldDate" class="timeline__date">{{ event.worldDate }}</span>
                <span class="c-badge" :class="{ 'c-badge--accent': event.chapter !== null }">
                  {{ event.chapter !== null ? t('timeline.chapter', { number: event.chapter }) : t('timeline.offscreen') }}
                </span>
                <span v-if="!event.revealed" class="c-badge">
                  <EyeOff aria-hidden="true" />
                  {{ t('timeline.secret') }}
                </span>
              </span>
              <span class="timeline__summary">{{ event.summary }}</span>
            </span>
          </button>

          <div v-if="!filtering" class="timeline__move">
            <UiButton
              variant="ghost"
              size="sm"
              icon
              :disabled="moving || events[0].id === event.id"
              :aria-label="t('timeline.moveUp', { title: event.title })"
              @click="move(event, -1)"
            >
              <ArrowUp aria-hidden="true" />
            </UiButton>
            <UiButton
              variant="ghost"
              size="sm"
              icon
              :disabled="moving || events[events.length - 1].id === event.id"
              :aria-label="t('timeline.moveDown', { title: event.title })"
              @click="move(event, 1)"
            >
              <ArrowDown aria-hidden="true" />
            </UiButton>
          </div>
        </li>
      </ol>
    </template>

    <EventPanel
      :open="panelOpen"
      :book-id="bookId"
      :event-id="creating ? null : eventParam"
      :default-id="nextId"
      :default-order="nextOrder"
      :lexicon="lexicon"
      @close="closePanel"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
