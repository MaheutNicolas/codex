<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { Plus, Trash2, X } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTagInput from '@/components/ui/UiTagInput.vue'
import * as eventsApi from '@/api/events'
import * as participantsApi from '@/api/participants'
import { typeIcon } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { SLUG_PATTERN } from '@/utils/text'

const props = defineProps({
  open: Boolean,
  bookId: { type: [String, Number], required: true },
  eventId: { type: String, default: null }, // null: a new event is being created
  defaultId: { type: String, default: '' }, // suggested identifier of a new event
  defaultOrder: { type: Number, default: 1 }, // suggested world order of a new event
  lexicon: { type: Array, default: () => [] }, // knowledge entries that can take part in an event
})

// `close` is sent once the panel really wants to close: after a save, a deletion,
// or when the user dismissed it and confirmed they accept to lose their changes.
const emit = defineEmits(['close', 'saved', 'deleted'])

const toast = useToast()

const isCreate = computed(() => props.eventId === null)

const form = reactive(blank())
const errors = reactive({})
const snapshot = ref('')
const original = ref([]) // the participants as the server knows them
const titleField = ref(null)
const picked = ref('') // the knowledge entry chosen in the "add a participant" row
const pickedRole = ref('')
const state = reactive({
  loading: false,
  saving: false,
  removing: false,
  discardOpen: false,
  removalOpen: false,
})

const dirty = computed(() => JSON.stringify(form) !== snapshot.value)

const entryById = computed(() => new Map(props.lexicon.map((entry) => [entry.id, entry])))
const pickOptions = computed(() => {
  const taken = new Set(form.participants.map((participant) => participant.knowledgeId))
  return [
    { value: '', label: t('timeline.form.pick') },
    ...props.lexicon.filter((entry) => !taken.has(entry.id)).map((entry) => ({ value: entry.id, label: entry.name })),
  ]
})

function blank() {
  return {
    id: props.defaultId,
    title: '',
    summary: '',
    detail: '',
    worldOrder: String(props.defaultOrder),
    worldDate: '',
    chapter: '',
    revealed: true,
    tags: [],
    participants: [], // [{ knowledgeId, role }]
  }
}

function fill(event, participants) {
  Object.assign(form, {
    id: event.id,
    title: event.title,
    summary: event.summary,
    detail: event.detail ?? '',
    worldOrder: String(event.worldOrder),
    worldDate: event.worldDate ?? '',
    chapter: event.chapter === null ? '' : String(event.chapter),
    revealed: event.revealed,
    tags: [...event.tags],
    participants: participants.map(({ knowledgeId, role }) => ({ knowledgeId, role: role ?? '' })),
  })
  original.value = participants.map(({ knowledgeId, role }) => ({ knowledgeId, role: role ?? '' }))
}

function clearErrors() {
  Object.keys(errors).forEach((field) => delete errors[field])
}

function resetForm() {
  clearErrors()
  Object.assign(form, blank())
  original.value = []
  picked.value = ''
  pickedRole.value = ''
  snapshot.value = JSON.stringify(form)
}

// Every time the panel opens (or shows another event), it starts from a clean state.
watch(
  () => [props.open, props.eventId],
  async ([open]) => {
    if (!open) return
    if (isCreate.value) {
      resetForm()
      return
    }

    clearErrors()
    picked.value = ''
    pickedRole.value = ''
    state.loading = true
    try {
      const [event, participants] = await Promise.all([
        eventsApi.get(props.bookId, props.eventId),
        participantsApi.listForEvent(props.bookId, props.eventId),
      ])
      fill(event, participants)
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
for (const field of ['id', 'title', 'summary', 'detail', 'worldOrder', 'worldDate', 'chapter', 'tags']) {
  watch(
    () => form[field],
    () => delete errors[field],
  )
}

function addParticipant() {
  if (!picked.value) return
  form.participants.push({ knowledgeId: picked.value, role: pickedRole.value.trim() })
  picked.value = ''
  pickedRole.value = ''
}

function removeParticipant(knowledgeId) {
  form.participants = form.participants.filter((participant) => participant.knowledgeId !== knowledgeId)
}

const nameOf = (knowledgeId) => entryById.value.get(knowledgeId)?.name ?? knowledgeId

function validate() {
  clearErrors()
  if (!form.title.trim()) errors.title = t('timeline.form.errors.titleRequired')
  if (!form.summary.trim()) errors.summary = t('timeline.form.errors.summaryRequired')
  if (!/^-?\d{1,9}$/.test(form.worldOrder.trim())) errors.worldOrder = t('timeline.form.errors.orderInvalid')
  if (form.chapter.trim() && !/^[1-9]\d{0,8}$/.test(form.chapter.trim())) {
    errors.chapter = t('timeline.form.errors.chapterInvalid')
  }
  if (isCreate.value) {
    if (!form.id) errors.id = t('timeline.form.errors.idRequired')
    else if (form.id.length > 100 || !SLUG_PATTERN.test(form.id)) errors.id = t('timeline.form.errors.idFormat')
  }

  return Object.keys(errors).length === 0
}

/** Brings the participants of the server in line with the form: removals, role changes, then additions. */
async function syncParticipants(eventId) {
  const wanted = new Map(form.participants.map((participant) => [participant.knowledgeId, participant.role]))
  const known = new Map(original.value.map((participant) => [participant.knowledgeId, participant.role]))
  const roleOrNull = (role) => role.trim() || null

  for (const knowledgeId of known.keys()) {
    if (!wanted.has(knowledgeId)) await participantsApi.remove(props.bookId, eventId, knowledgeId)
  }
  for (const [knowledgeId, role] of wanted) {
    if (!known.has(knowledgeId)) {
      await participantsApi.create(props.bookId, { eventId, knowledgeId, role: roleOrNull(role) })
    } else if (known.get(knowledgeId) !== role) {
      await participantsApi.update(props.bookId, eventId, knowledgeId, roleOrNull(role))
    }
  }
}

async function save(createAnother = false) {
  if (!validate()) return

  state.saving = true
  const data = {
    title: form.title.trim(),
    summary: form.summary.trim(),
    detail: form.detail.trim() || null,
    worldOrder: Number(form.worldOrder.trim()),
    worldDate: form.worldDate.trim() || null,
    chapter: form.chapter.trim() ? Number(form.chapter.trim()) : null,
    revealed: form.revealed,
    tags: form.tags,
  }

  let event
  try {
    event = isCreate.value
      ? await eventsApi.create(props.bookId, { id: form.id, ...data })
      : await eventsApi.update(props.bookId, props.eventId, data)
  } catch (error) {
    showSaveError(error)
    state.saving = false
    return
  }

  // The event exists from here on: a failure while linking its participants must not make the panel
  // believe the event is still new, so it reports the problem and closes.
  let participantsFailed = false
  try {
    await syncParticipants(event.id)
  } catch (error) {
    participantsFailed = true
    toast.error(`${t('timeline.participantsFailed')} ${errorMessage(error)}`)
  }

  toast.success(t(isCreate.value ? 'timeline.created' : 'timeline.saved'))
  emit('saved', event)
  state.saving = false

  if (createAnother && !participantsFailed) {
    await nextTick() // the parent has updated the suggested identifier and order
    resetForm()
    titleField.value?.focus()
  } else {
    emit('close')
  }
}

function showSaveError(error) {
  if (error.code === 'ID_ALREADY_EXISTS') {
    errors.id = t('timeline.form.errors.idExists')
    return
  }
  // The API reports each faulty field; its messages are in English, so only the first one is shown as is.
  for (const [field, messages] of Object.entries(error.details?.fields ?? {})) errors[field] = messages[0]
  toast.error(errorMessage(error))
}

async function remove() {
  state.removing = true
  try {
    await eventsApi.remove(props.bookId, props.eventId)
    toast.success(t('timeline.deleted'))
    state.removalOpen = false
    emit('deleted', props.eventId)
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
    :title="isCreate ? t('timeline.form.createTitle') : t('timeline.form.editTitle')"
    @dismiss="requestClose"
  >
    <p v-if="state.loading" class="u-muted">{{ t('common.loading') }}</p>

    <form v-else id="event-form" class="event-form" novalidate @submit.prevent="save()">
      <UiField
        ref="titleField"
        v-model="form.title"
        :label="t('timeline.form.title')"
        :placeholder="t('timeline.form.titlePlaceholder')"
        :error="errors.title"
        :maxlength="255"
        :autofocus="isCreate"
        autocomplete="off"
      />

      <UiField
        v-model="form.id"
        :label="t('timeline.form.id')"
        :hint="isCreate ? t('timeline.form.idHint') : t('timeline.form.idLocked')"
        :error="errors.id"
        :maxlength="100"
        :readonly="!isCreate"
        autocomplete="off"
      />

      <UiField
        v-model="form.summary"
        multiline
        :rows="3"
        :label="t('timeline.form.summary')"
        :hint="t('timeline.form.summaryHint')"
        :error="errors.summary"
      />

      <div class="event-form__row">
        <UiField
          v-model="form.worldOrder"
          :label="t('timeline.form.worldOrder')"
          :hint="t('timeline.form.worldOrderHint')"
          :error="errors.worldOrder"
          :maxlength="10"
          autocomplete="off"
        />
        <UiField
          v-model="form.chapter"
          :label="t('timeline.form.chapter')"
          :hint="t('timeline.form.chapterHint')"
          :error="errors.chapter"
          :maxlength="9"
          autocomplete="off"
        />
      </div>

      <UiField
        v-model="form.worldDate"
        :label="t('timeline.form.worldDate')"
        :hint="t('timeline.form.worldDateHint')"
        :error="errors.worldDate"
        :maxlength="100"
        autocomplete="off"
      />

      <label class="c-check">
        <input v-model="form.revealed" type="checkbox" class="c-check__input" />
        <span class="c-check__text">
          {{ t('timeline.form.revealed') }}
          <span class="c-check__hint">{{ t('timeline.form.revealedHint') }}</span>
        </span>
      </label>

      <UiField
        v-model="form.detail"
        multiline
        :rows="8"
        :label="t('timeline.form.detail')"
        :hint="t('timeline.form.detailHint')"
        :error="errors.detail"
      />

      <UiTagInput
        v-model="form.tags"
        :label="t('timeline.form.tags')"
        :hint="t('timeline.form.tagsHint')"
        :placeholder="t('timeline.form.tagsPlaceholder')"
      />

      <section class="event-form__participants" aria-labelledby="participants-title">
        <h3 id="participants-title" class="event-form__section-title">{{ t('timeline.form.participants') }}</h3>
        <p class="c-field__hint">{{ t('timeline.form.participantsHint') }}</p>

        <p v-if="form.participants.length === 0" class="u-muted">{{ t('timeline.form.noParticipants') }}</p>
        <ul v-else class="participants">
          <li v-for="participant in form.participants" :key="participant.knowledgeId" class="participants__row">
            <component
              :is="typeIcon(entryById.get(participant.knowledgeId)?.type)"
              class="participants__icon"
              aria-hidden="true"
            />
            <span class="participants__name">
              {{ entryById.has(participant.knowledgeId) ? nameOf(participant.knowledgeId) : t('timeline.form.unknownEntry') }}
            </span>
            <input
              v-model="participant.role"
              class="c-field__input participants__role"
              type="text"
              maxlength="50"
              :placeholder="t('timeline.form.roleLabel')"
              :aria-label="t('timeline.form.roleOf', { name: nameOf(participant.knowledgeId) })"
              autocomplete="off"
            />
            <UiButton
              variant="ghost"
              size="sm"
              icon
              :aria-label="t('timeline.form.removeParticipant', { name: nameOf(participant.knowledgeId) })"
              @click="removeParticipant(participant.knowledgeId)"
            >
              <X aria-hidden="true" />
            </UiButton>
          </li>
        </ul>

        <div class="participants__add">
          <UiSelect v-model="picked" :label="t('timeline.form.addLabel')" :options="pickOptions" />
          <UiField
            v-model="pickedRole"
            :label="t('timeline.form.roleLabel')"
            :placeholder="t('timeline.form.rolePlaceholder')"
            :maxlength="50"
            autocomplete="off"
            @keydown.enter.prevent="addParticipant"
          />
          <UiButton :disabled="!picked" @click="addParticipant">
            <Plus aria-hidden="true" />
            {{ t('timeline.form.add') }}
          </UiButton>
        </div>
      </section>
    </form>

    <template #footer>
      <UiButton v-if="!isCreate" variant="ghost" class="c-dialog__spacer" @click="state.removalOpen = true">
        <Trash2 aria-hidden="true" />
        {{ t('common.delete') }}
      </UiButton>
      <UiButton @click="requestClose">{{ t('common.cancel') }}</UiButton>
      <UiButton v-if="isCreate" :loading="state.saving" @click="save(true)">
        {{ t('timeline.form.saveAndNext') }}
      </UiButton>
      <UiButton type="submit" form="event-form" variant="primary" :loading="state.saving" :disabled="state.loading">
        {{ isCreate ? t('common.create') : t('common.save') }}
      </UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.discardOpen" :title="t('timeline.discardTitle')" @dismiss="state.discardOpen = false">
    <p>{{ t('timeline.discardText') }}</p>
    <template #footer>
      <UiButton autofocus @click="state.discardOpen = false">{{ t('timeline.keepEditing') }}</UiButton>
      <UiButton variant="danger" @click="discard">{{ t('timeline.discard') }}</UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.removalOpen" :title="t('timeline.deleteTitle')" @dismiss="state.removalOpen = false">
    <p>{{ t('timeline.deleteText', { title: form.title }) }}</p>
    <template #footer>
      <UiButton autofocus @click="state.removalOpen = false">{{ t('common.cancel') }}</UiButton>
      <UiButton variant="danger" :loading="state.removing" @click="remove">{{ t('common.delete') }}</UiButton>
    </template>
  </UiDialog>
</template>
