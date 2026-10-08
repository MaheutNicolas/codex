<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { ArrowLeftRight, Trash2 } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import * as relationsApi from '@/api/relations'
import { RELATION_TYPES } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { pairKey } from '@/utils/relations'
import { SLUG_PATTERN } from '@/utils/text'

const props = defineProps({
  open: Boolean,
  bookId: { type: [String, Number], required: true },
  relationId: { type: String, default: null }, // null: a new state is being created
  nextId: { type: String, default: '' }, // suggested identifier of a new relation
  lexicon: { type: Array, default: () => [] }, // the entries a relation can link
  relations: { type: Array, default: () => [] }, // every state of the book, to show the history of the pair
  defaultSource: { type: String, default: '' },
  defaultTarget: { type: String, default: '' },
})

// `close` is sent once the panel really wants to close: after a save, a deletion,
// or when the user dismissed it and confirmed they accept to lose their changes.
const emit = defineEmits(['close', 'saved', 'deleted'])

const toast = useToast()

const isCreate = computed(() => props.relationId === null)

const form = reactive(blank())
const errors = reactive({})
const snapshot = ref('')
const state = reactive({
  loading: false,
  saving: false,
  removing: false,
  discardOpen: false,
  removalOpen: false,
})

const dirty = computed(() => JSON.stringify(form) !== snapshot.value)

function blank(source = props.defaultSource, target = props.defaultTarget) {
  return { id: props.nextId, sourceId: source, targetId: target, type: 'ally', chapter: '', revealed: true, note: '' }
}

const entryOptions = computed(() => [
  { value: '', label: t('relations.form.pick') },
  ...[...props.lexicon].sort((a, b) => a.name.localeCompare(b.name)).map((entry) => ({ value: entry.id, label: entry.name })),
])
const typeOptions = computed(() => RELATION_TYPES.map(({ value }) => ({ value, label: t(`relations.types.${value}`) })))

const nameOf = (id) => props.lexicon.find((entry) => entry.id === id)?.name ?? id

/** The relation written as a sentence: it makes the direction of "mentor" or "serves" impossible to misread. */
const preview = computed(() =>
  form.sourceId && form.targetId
    ? t(`relations.sentences.${form.type}`, { source: nameOf(form.sourceId), target: nameOf(form.targetId) })
    : '',
)

// The other states of the pair being edited, to see where this one fits in the story.
const history = computed(() => {
  if (!form.sourceId || !form.targetId) return []
  const key = pairKey(form)

  return props.relations
    .filter((relation) => pairKey(relation) === key && relation.id !== props.relationId)
    .sort((a, b) => (a.chapter ?? 0) - (b.chapter ?? 0) || (a.id < b.id ? -1 : 1))
})
const chapterLabel = (chapter) => (chapter === null ? t('relations.fromStart') : t('relations.chapter', { number: chapter }))

function fill(relation) {
  Object.assign(form, {
    id: relation.id,
    sourceId: relation.sourceId,
    targetId: relation.targetId,
    type: relation.type,
    chapter: relation.chapter === null ? '' : String(relation.chapter),
    revealed: relation.revealed,
    note: relation.note ?? '',
  })
}

function clearErrors() {
  Object.keys(errors).forEach((field) => delete errors[field])
}

function resetForm(source, target) {
  clearErrors()
  Object.assign(form, blank(source, target))
  snapshot.value = JSON.stringify(form)
}

// Every time the panel opens (or shows another relation), it starts from a clean state.
watch(
  () => [props.open, props.relationId],
  async ([open]) => {
    if (!open) return
    if (isCreate.value) {
      resetForm()
      return
    }

    clearErrors()
    state.loading = true
    try {
      fill(await relationsApi.get(props.bookId, props.relationId))
      snapshot.value = JSON.stringify(form)
    } catch (error) {
      toast.error(errorMessage(error))
      emit('close')
    } finally {
      state.loading = false
    }
  },
  { immediate: true },
)

// An error goes away as soon as its field is edited, not only at the next save.
for (const field of ['id', 'sourceId', 'targetId', 'type', 'chapter', 'note']) {
  watch(
    () => form[field],
    () => delete errors[field],
  )
}
// Moving a state to another pair changes what "already has a state at this chapter" means.
watch(() => [form.sourceId, form.targetId], () => delete errors.chapter)

function swap() {
  ;[form.sourceId, form.targetId] = [form.targetId, form.sourceId]
}

function validate() {
  clearErrors()
  if (!form.sourceId) errors.sourceId = t('relations.form.errors.sourceRequired')
  if (!form.targetId) errors.targetId = t('relations.form.errors.targetRequired')
  else if (form.targetId === form.sourceId) errors.targetId = t('relations.form.errors.sameEntry')
  if (!form.type) errors.type = t('relations.form.errors.typeRequired')
  if (form.chapter.trim() && !/^[1-9]\d{0,8}$/.test(form.chapter.trim())) {
    errors.chapter = t('relations.form.errors.chapterInvalid')
  }
  if (form.note.length > 500) errors.note = t('relations.form.errors.noteTooLong')
  if (isCreate.value) {
    if (!form.id) errors.id = t('relations.form.errors.idRequired')
    else if (form.id.length > 100 || !SLUG_PATTERN.test(form.id)) errors.id = t('relations.form.errors.idFormat')
  }

  return Object.keys(errors).length === 0
}

async function save(createAnother = false) {
  if (!validate()) return

  state.saving = true
  const data = {
    sourceId: form.sourceId,
    targetId: form.targetId,
    type: form.type,
    chapter: form.chapter.trim() ? Number(form.chapter.trim()) : null,
    revealed: form.revealed,
    note: form.note.trim() || null,
  }

  try {
    const relation = isCreate.value
      ? await relationsApi.create(props.bookId, { id: form.id, ...data })
      : await relationsApi.update(props.bookId, props.relationId, data)

    toast.success(t(isCreate.value ? 'relations.created' : 'relations.saved'))
    emit('saved', relation)

    if (createAnother) {
      await nextTick() // the parent has updated the suggested identifier and the history
      resetForm(form.sourceId, form.targetId)
    } else {
      emit('close')
    }
  } catch (error) {
    showSaveError(error)
  } finally {
    state.saving = false
  }
}

function showSaveError(error) {
  if (error.code === 'ID_ALREADY_EXISTS') {
    errors.id = t('relations.form.errors.idExists')
    return
  }
  if (error.code === 'RELATION_ALREADY_EXISTS') {
    errors.chapter = t('relations.form.errors.pairExists', { existing: error.details?.existing ?? '' })
    return
  }
  // The API reports each faulty field; its messages are in English, so only the first one is shown as is.
  for (const [field, messages] of Object.entries(error.details?.fields ?? {})) errors[field] = messages[0]
  toast.error(errorMessage(error))
}

async function remove() {
  state.removing = true
  try {
    await relationsApi.remove(props.bookId, props.relationId)
    toast.success(t('relations.deleted'))
    state.removalOpen = false
    emit('deleted', props.relationId)
    emit('close')
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    state.removing = false
  }
}

// Escape, the backdrop and the cross end up here: unsaved changes are never lost without asking.
function requestClose() {
  if (dirty.value && !state.saving) state.discardOpen = true
  else emit('close')
}

function discard() {
  state.discardOpen = false
  emit('close')
}
</script>

<template>
  <UiDialog
    :open="open"
    side
    :title="isCreate ? t('relations.form.createTitle') : t('relations.form.editTitle')"
    @dismiss="requestClose"
  >
    <p v-if="state.loading" class="u-muted">{{ t('common.loading') }}</p>

    <form v-else id="relation-form" class="event-form" novalidate @submit.prevent="save()">
      <div class="relation-form__entries">
        <UiSelect v-model="form.sourceId" :label="t('relations.form.source')" :options="entryOptions" :error="errors.sourceId" />
        <UiButton
          variant="ghost"
          icon
          class="relation-form__swap"
          :aria-label="t('relations.form.swap')"
          :title="t('relations.form.swap')"
          @click="swap"
        >
          <ArrowLeftRight aria-hidden="true" />
        </UiButton>
        <UiSelect v-model="form.targetId" :label="t('relations.form.target')" :options="entryOptions" :error="errors.targetId" />
      </div>

      <UiSelect
        v-model="form.type"
        :label="t('relations.form.type')"
        :hint="t('relations.form.typeHint')"
        :options="typeOptions"
        :error="errors.type"
      />

      <p v-if="preview" class="relation-form__preview">
        <span class="relation-form__preview-label">{{ t('relations.form.preview') }}</span>
        {{ preview }}
      </p>

      <UiField
        v-model="form.chapter"
        :label="t('relations.form.chapter')"
        :hint="t('relations.form.chapterHint')"
        :error="errors.chapter"
        :maxlength="9"
        autocomplete="off"
      />

      <label class="c-check">
        <input v-model="form.revealed" type="checkbox" class="c-check__input" />
        <span class="c-check__text">
          {{ t('relations.form.revealed') }}
          <span class="c-check__hint">{{ t('relations.form.revealedHint') }}</span>
        </span>
      </label>

      <UiField
        v-model="form.note"
        :label="t('relations.form.note')"
        :hint="t('relations.form.noteHint')"
        :error="errors.note"
        :maxlength="500"
        autocomplete="off"
      />

      <UiField
        v-model="form.id"
        :label="t('relations.form.id')"
        :hint="isCreate ? t('relations.form.idHint') : t('relations.form.idLocked')"
        :error="errors.id"
        :maxlength="100"
        :readonly="!isCreate"
        autocomplete="off"
      />

      <section class="relation-form__history" aria-labelledby="relation-history-title">
        <h3 id="relation-history-title" class="event-form__section-title">{{ t('relations.form.history') }}</h3>
        <p v-if="history.length === 0" class="u-muted">{{ t('relations.form.historyNone') }}</p>
        <ul v-else class="relation-form__states">
          <li v-for="other in history" :key="other.id">
            <span class="relation-form__state-chapter">{{ chapterLabel(other.chapter) }}</span>
            {{ t(`relations.types.${other.type}`) }}
            <span v-if="!other.revealed" class="u-muted">· {{ t('relations.secret') }}</span>
          </li>
        </ul>
      </section>
    </form>

    <template #footer>
      <UiButton v-if="!isCreate" variant="ghost" class="c-dialog__spacer" @click="state.removalOpen = true">
        <Trash2 aria-hidden="true" />
        {{ t('common.delete') }}
      </UiButton>
      <UiButton @click="requestClose">{{ t('common.cancel') }}</UiButton>
      <UiButton v-if="isCreate" :loading="state.saving" @click="save(true)">
        {{ t('relations.form.saveAndNext') }}
      </UiButton>
      <UiButton type="submit" form="relation-form" variant="primary" :loading="state.saving" :disabled="state.loading">
        {{ isCreate ? t('common.create') : t('common.save') }}
      </UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.discardOpen" :title="t('relations.discardTitle')" @dismiss="state.discardOpen = false">
    <p>{{ t('relations.discardText') }}</p>
    <template #footer>
      <UiButton autofocus @click="state.discardOpen = false">{{ t('relations.keepEditing') }}</UiButton>
      <UiButton variant="danger" @click="discard">{{ t('relations.discard') }}</UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.removalOpen" :title="t('relations.deleteTitle')" @dismiss="state.removalOpen = false">
    <p>{{ t('relations.deleteText', { sentence: preview }) }}</p>
    <template #footer>
      <UiButton autofocus @click="state.removalOpen = false">{{ t('common.cancel') }}</UiButton>
      <UiButton variant="danger" :loading="state.removing" @click="remove">{{ t('common.delete') }}</UiButton>
    </template>
  </UiDialog>
</template>
