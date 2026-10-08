<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { EyeOff, Network, Plus, Search } from '@lucide/vue'
import RelationPanel from '@/components/relations/RelationPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import * as knowledgeApi from '@/api/knowledge'
import * as relationsApi from '@/api/relations'
import { RELATION_TYPES, canHaveRelations, relationIcon } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t, tn } from '@/locales'
import { compareNames } from '@/utils/format'
import { pairKey } from '@/utils/relations'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const bookId = computed(() => route.params.bookId)
const relations = ref([]) // every state of every relation, by chapter (the start of the book first)
const lexicon = ref([]) // the entries a relation can link
const loading = ref(true)
const typeFilter = ref('all') // 'all' or a type of relation
const visibility = ref('all') // 'all', 'revealed' or 'secret'

async function load() {
  loading.value = true
  try {
    const [list, entries] = await Promise.all([relationsApi.listAll(bookId.value), knowledgeApi.lexicon(bookId.value)])
    relations.value = list
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
    typeFilter.value = 'all'
    visibility.value = 'all'
    load()
  },
  { immediate: true },
)

// ---- The URL: ?character=aldric&with=mira filter the list, ?relation=rel-0001 and ?new open the panel -------

const queryString = (name) => (typeof route.query[name] === 'string' ? route.query[name] : '')

/** Changes some parameters of the URL and keeps the others (a null value removes one). */
function setQuery(patch) {
  const query = { ...route.query, ...patch }
  for (const name of Object.keys(query)) if (query[name] === null || query[name] === '') delete query[name]
  router.replace({ query })
}

const character = computed({
  get: () => queryString('character'),
  set: (value) => setQuery({ character: value, with: null }),
})
const partner = computed({
  get: () => (character.value ? queryString('with') : ''),
  set: (value) => setQuery({ with: value }),
})

// ---- Names and labels ----------------------------------------------------------------------------------

const entryById = computed(() => new Map(lexicon.value.map((entry) => [entry.id, entry])))
const nameOf = (id) => entryById.value.get(id)?.name ?? id
const typeLabel = (type) => t(`relations.types.${type}`)
const sentence = (relation) =>
  t(`relations.sentences.${relation.type}`, { source: nameOf(relation.sourceId), target: nameOf(relation.targetId) })
const chapterLabel = (relation) =>
  relation.chapter === null ? t('relations.fromStart') : t('relations.chapter', { number: relation.chapter })

// An entry that cannot have relations, but has some from before the rule, stays listed so that they can be found.
const inRelations = computed(() => new Set(relations.value.flatMap((relation) => [relation.sourceId, relation.targetId])))

const characterOptions = computed(() => [
  { value: '', label: t('relations.allCharacters') },
  ...lexicon.value
    .filter((entry) => canHaveRelations(entry.type) || inRelations.value.has(entry.id))
    .sort((a, b) => compareNames(a.name, b.name))
    .map((entry) => ({ value: entry.id, label: entry.name })),
])

// Only the entries that share a relation with the chosen one make sense as "with".
const partnerOptions = computed(() => {
  const partners = new Set()
  for (const relation of relations.value) {
    if (relation.sourceId === character.value) partners.add(relation.targetId)
    if (relation.targetId === character.value) partners.add(relation.sourceId)
  }

  return [
    { value: '', label: t('relations.anyone') },
    ...[...partners].map((id) => ({ value: id, label: nameOf(id) })).sort((a, b) => compareNames(a.label, b.label)),
  ]
})

const typeOptions = computed(() => [
  { value: 'all', label: t('relations.allTypes') },
  ...RELATION_TYPES.map(({ value }) => ({ value, label: t(`relations.types.${value}`) })),
])

// ---- Filters ---------------------------------------------------------------------------------------------

const visible = computed(() =>
  relations.value.filter((relation) => {
    if (character.value && relation.sourceId !== character.value && relation.targetId !== character.value) return false
    if (partner.value && relation.sourceId !== partner.value && relation.targetId !== partner.value) return false
    if (typeFilter.value !== 'all' && relation.type !== typeFilter.value) return false
    if (visibility.value === 'revealed' && !relation.revealed) return false
    if (visibility.value === 'secret' && relation.revealed) return false

    return true
  }),
)

function resetFilters() {
  typeFilter.value = 'all'
  visibility.value = 'all'
  setQuery({ character: null, with: null })
}

// The last state of each pair, over the whole book: it is the relation as it stands at the end of the story.
const latestIds = computed(() => {
  const latest = new Map()
  for (const relation of relations.value) {
    const key = pairKey(relation)
    const current = latest.get(key)
    if (!current || (relation.chapter ?? 0) >= (current.chapter ?? 0)) latest.set(key, relation)
  }

  return new Set([...latest.values()].map((relation) => relation.id))
})

// ---- The edit panel is driven by the URL ------------------------------------------------------------------

const relationParam = computed(() => queryString('relation') || null)
const creating = computed(() => route.query.new !== undefined)
const panelOpen = computed(() => creating.value || relationParam.value !== null)

// Suggestions for a new relation: the next rel-NNNN identifier, and the entries of the current filter.
const nextId = computed(() => {
  const numbers = relations.value.map((relation) => /^rel-(\d+)$/.exec(relation.id)?.[1]).filter(Boolean).map(Number)
  return `rel-${String((numbers.length ? Math.max(...numbers) : 0) + 1).padStart(4, '0')}`
})

const openRelation = (id) => setQuery({ relation: id, new: null })
const openCreate = () => setQuery({ new: '1', relation: null })
const closePanel = () => setQuery({ new: null, relation: null })

// The list follows what the panel does, without asking the server again.
function onSaved(relation) {
  const row = {
    id: relation.id,
    sourceId: relation.sourceId,
    targetId: relation.targetId,
    type: relation.type,
    chapter: relation.chapter,
    revealed: relation.revealed,
    note: relation.note,
  }
  const index = relations.value.findIndex((existing) => existing.id === row.id)
  if (index >= 0) relations.value[index] = row
  else relations.value.push(row)
  relations.value.sort((a, b) => (a.chapter ?? 0) - (b.chapter ?? 0) || (a.id < b.id ? -1 : 1))
}

function onDeleted(id) {
  relations.value = relations.value.filter((relation) => relation.id !== id)
}

// ---- Keyboard: "n" starts a new relation when nothing is being typed ---------------------------------------

function onKeydown(event) {
  if (panelOpen.value) return
  const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target.isContentEditable
  if (event.key === 'n' && !typing && !event.ctrlKey && !event.metaKey) {
    event.preventDefault()
    openCreate()
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div class="page relations">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('relations.title') }}</h1>
        <p class="page__subtitle">{{ t('relations.subtitle') }}</p>
      </div>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('relations.new') }}
      </UiButton>
    </header>

    <div v-if="relations.length" class="library__toolbar">
      <UiSelect v-model="character" class="relations__select" :label="t('relations.character')" :options="characterOptions" />
      <UiSelect
        v-if="character"
        v-model="partner"
        class="relations__select"
        :label="t('relations.with')"
        :options="partnerOptions"
      />
      <UiSelect v-model="typeFilter" class="relations__select" :label="t('relations.typeLabel')" :options="typeOptions" />

      <div class="library__filters" role="group">
        <button
          v-for="option in ['all', 'revealed', 'secret']"
          :key="option"
          type="button"
          class="c-chip"
          :aria-pressed="visibility === option"
          @click="visibility = option"
        >
          {{ t(`relations.visibility.${option}`) }}
        </button>
      </div>
    </div>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <div v-else-if="relations.length === 0" class="c-empty">
      <span class="c-empty__icon"><Network aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('relations.empty.title') }}</h2>
      <p>{{ t('relations.empty.text') }}</p>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('relations.new') }}
      </UiButton>
    </div>

    <div v-else-if="visible.length === 0" class="c-empty">
      <span class="c-empty__icon"><Search aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('relations.noResults.title') }}</h2>
      <p>{{ t('relations.noResults.text') }}</p>
      <UiButton @click="resetFilters">{{ t('relations.noResults.reset') }}</UiButton>
    </div>

    <template v-else>
      <p class="library__count">{{ tn('relations.count', visible.length) }}</p>
      <p v-if="!character" class="u-muted relations__hint">{{ t('relations.searchHint') }}</p>
      <ol class="relations__list">
        <li
          v-for="(relation, index) in visible"
          :key="relation.id"
          class="relations__item"
          :class="{ 'relations__item--new-chapter': index > 0 && visible[index - 1].chapter !== relation.chapter }"
        >
          <button
            type="button"
            class="relations__row"
            :class="{ 'is-active': relation.id === relationParam, 'is-secret': !relation.revealed }"
            @click="openRelation(relation.id)"
          >
            <span class="relations__chapter" :class="{ 'is-start': relation.chapter === null }">
              {{ chapterLabel(relation) }}
            </span>
            <span class="relations__body">
              <span class="relations__title-line">
                <span class="relations__title">{{ sentence(relation) }}</span>
                <span class="c-badge c-badge--accent">
                  <component :is="relationIcon(relation.type)" aria-hidden="true" />
                  {{ typeLabel(relation.type) }}
                </span>
                <span v-if="!relation.revealed" class="c-badge">
                  <EyeOff aria-hidden="true" />
                  {{ t('relations.secret') }}
                </span>
                <span v-if="latestIds.has(relation.id)" class="c-badge">{{ t('relations.latest') }}</span>
              </span>
              <span v-if="relation.note" class="relations__note">{{ relation.note }}</span>
            </span>
          </button>
        </li>
      </ol>
    </template>

    <RelationPanel
      :open="panelOpen"
      :book-id="bookId"
      :relation-id="creating ? null : relationParam"
      :next-id="nextId"
      :lexicon="lexicon"
      :relations="relations"
      :default-source="character"
      :default-target="partner"
      @close="closePanel"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
