<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Library, Plus, Search } from '@lucide/vue'
import KnowledgePanel from '@/components/library/KnowledgePanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import * as knowledgeApi from '@/api/knowledge'
import { KNOWLEDGE_TYPES, typeIcon } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t, tn } from '@/locales'
import { normalize } from '@/utils/text'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = computed(() => route.params.bookId)
const entries = ref([])
const loading = ref(true)
const query = ref('')
const typeFilter = ref('all') // 'all' or a knowledge type
const searchInput = ref(null)

async function load() {
  loading.value = true
  try {
    entries.value = await knowledgeApi.listAll(bookId.value)
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

watch(
  bookId,
  () => {
    query.value = ''
    typeFilter.value = 'all'
    load()
  },
  { immediate: true },
)

// ---- Search and filters -------------------------------------------------------------------

const counts = computed(() =>
  Object.fromEntries(
    KNOWLEDGE_TYPES.map(({ value }) => [value, entries.value.filter((entry) => entry.type === value).length]),
  ),
)

// How well an entry matches the search: 0 = best (the name starts with it), -1 = no match.
function rank(entry, search) {
  const name = normalize(entry.name)
  if (name.startsWith(search)) return 0
  if (name.includes(search)) return 1
  if (entry.aliases.some((alias) => normalize(alias).includes(search))) return 2
  if (entry.id.includes(search) || normalize(entry.summary).includes(search)) return 3
  return -1
}

const visible = computed(() => {
  const byType = entries.value.filter((entry) => typeFilter.value === 'all' || entry.type === typeFilter.value)
  const search = normalize(query.value.trim())
  if (!search) return byType

  return byType
    .map((entry) => ({ entry, score: rank(entry, search) }))
    .filter(({ score }) => score >= 0)
    .sort((a, b) => a.score - b.score || a.entry.name.localeCompare(b.entry.name, 'fr'))
    .map(({ entry }) => entry)
})

function resetSearch() {
  query.value = ''
  typeFilter.value = 'all'
}

// ---- The edit panel is driven by the URL: ?entry=aldric edits, ?new creates --------------------

const entryParam = computed(() => (typeof route.query.entry === 'string' ? route.query.entry : null))
const creating = computed(() => route.query.new !== undefined)
const panelOpen = computed(() => creating.value || entryParam.value !== null)
const newEntryType = computed(() => (typeFilter.value === 'all' ? 'character' : typeFilter.value))

function openEntry(id) {
  router.replace({ query: { entry: id } })
}

function openCreate() {
  router.replace({ query: { new: '1' } })
}

function closePanel() {
  router.replace({ query: {} })
}

// The list follows what the panel does, without asking the server again.
function onSaved(entry) {
  const row = { id: entry.id, name: entry.name, type: entry.type, summary: entry.summary, aliases: entry.aliases }
  const index = entries.value.findIndex((existing) => existing.id === row.id)
  if (index >= 0) entries.value[index] = row
  else entries.value.push(row)
  entries.value.sort((a, b) => a.name.localeCompare(b.name, 'fr'))
}

function onDeleted(id) {
  entries.value = entries.value.filter((entry) => entry.id !== id)
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
  <div class="page library">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('library.title') }}</h1>
        <p class="page__subtitle">{{ t('library.subtitle') }}</p>
      </div>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('library.new') }}
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
          :placeholder="t('library.search')"
          :aria-label="t('library.searchLabel')"
          autocomplete="off"
        />
        <kbd v-if="!query" class="c-search__hint" aria-hidden="true">/</kbd>
      </div>

      <div class="library__filters" role="group">
        <button type="button" class="c-chip" :aria-pressed="typeFilter === 'all'" @click="typeFilter = 'all'">
          {{ t('library.all') }}
          <span class="c-chip__count">{{ entries.length }}</span>
        </button>
        <button
          v-for="type in KNOWLEDGE_TYPES"
          :key="type.value"
          type="button"
          class="c-chip"
          :aria-pressed="typeFilter === type.value"
          @click="typeFilter = type.value"
        >
          <component :is="type.icon" aria-hidden="true" />
          {{ t(`library.typesPlural.${type.value}`) }}
          <span class="c-chip__count">{{ counts[type.value] }}</span>
        </button>
      </div>
    </div>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <div v-else-if="entries.length === 0" class="c-empty">
      <span class="c-empty__icon"><Library aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('library.empty.title') }}</h2>
      <p>{{ t('library.empty.text') }}</p>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('library.new') }}
      </UiButton>
    </div>

    <div v-else-if="visible.length === 0" class="c-empty">
      <span class="c-empty__icon"><Search aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('library.noResults.title') }}</h2>
      <p>{{ t('library.noResults.text', { query: query.trim() }) }}</p>
      <UiButton @click="resetSearch">{{ t('library.noResults.reset') }}</UiButton>
    </div>

    <template v-else>
      <p class="library__count">{{ tn('library.count', visible.length) }}</p>
      <ul class="library__list">
        <li v-for="entry in visible" :key="entry.id">
          <button
            type="button"
            class="library__row"
            :class="{ 'is-active': entry.id === entryParam }"
            @click="openEntry(entry.id)"
          >
            <span class="library__icon"><component :is="typeIcon(entry.type)" aria-hidden="true" /></span>
            <span class="library__body">
              <span class="library__title-line">
                <span class="library__name">{{ entry.name }}</span>
                <span class="c-badge">{{ t(`library.types.${entry.type}`) }}</span>
              </span>
              <span v-if="entry.aliases.length" class="library__aliases">
                {{ t('library.aliases', { list: entry.aliases.join(', ') }) }}
              </span>
              <span class="library__summary">{{ entry.summary }}</span>
            </span>
          </button>
        </li>
      </ul>
    </template>

    <KnowledgePanel
      :open="panelOpen"
      :book-id="bookId"
      :entry-id="creating ? null : entryParam"
      :default-type="newEntryType"
      @close="closePanel"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
