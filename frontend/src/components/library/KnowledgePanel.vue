<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Network, Trash2 } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTagInput from '@/components/ui/UiTagInput.vue'
import * as knowledgeApi from '@/api/knowledge'
import { KNOWLEDGE_TYPES } from '@/constants'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { SLUG_PATTERN, slugify } from '@/utils/text'

const props = defineProps({
  open: Boolean,
  bookId: { type: [String, Number], required: true },
  entryId: { type: String, default: null }, // null: a new entry is being created
  defaultType: { type: String, default: 'character' },
})

// `close` is sent once the panel really wants to close: after a save, a deletion,
// or when the user dismissed it and confirmed they accept to lose their changes.
const emit = defineEmits(['close', 'saved', 'deleted'])

const toast = useToast()
const router = useRouter()

const isCreate = computed(() => props.entryId === null)
const typeOptions = computed(() => KNOWLEDGE_TYPES.map(({ value }) => ({ value, label: t(`library.types.${value}`) })))

const form = reactive(blank())
const errors = reactive({})
const snapshot = ref('')
const nameField = ref(null)
const state = reactive({
  loading: false,
  saving: false,
  removing: false,
  discardOpen: false,
  removalOpen: false,
  // While the identifier of a new entry was never typed by hand, it follows the name.
  idEdited: false,
})

const dirty = computed(() => JSON.stringify(form) !== snapshot.value)

function blank(type = props.defaultType) {
  return { id: '', type, name: '', summary: '', description: '', aliases: [] }
}

function fill(entry) {
  Object.assign(form, {
    id: entry.id,
    type: entry.type,
    name: entry.name,
    summary: entry.summary,
    description: entry.description ?? '',
    aliases: [...entry.aliases],
  })
}

function clearErrors() {
  Object.keys(errors).forEach((field) => delete errors[field])
}

function resetForm(type) {
  clearErrors()
  state.idEdited = false
  Object.assign(form, blank(type))
  snapshot.value = JSON.stringify(form)
}

// Every time the panel opens (or shows another entry), it starts from a clean state.
watch(
  () => [props.open, props.entryId],
  async ([open]) => {
    if (!open) return
    if (isCreate.value) {
      resetForm()
      return
    }

    clearErrors()
    state.idEdited = true
    state.loading = true
    try {
      fill(await knowledgeApi.get(props.bookId, props.entryId))
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

watch(
  () => form.name,
  (name) => {
    if (isCreate.value && !state.idEdited) form.id = slugify(name)
  },
)

// An error goes away as soon as its field is edited, not only at the next save.
for (const field of ['name', 'type', 'summary', 'description', 'id', 'aliases']) {
  watch(
    () => form[field],
    () => delete errors[field],
  )
}

function onIdInput(value) {
  state.idEdited = value !== ''
}

function validate() {
  clearErrors()
  if (!form.name.trim()) errors.name = t('library.form.errors.nameRequired')
  if (!form.summary.trim()) errors.summary = t('library.form.errors.summaryRequired')
  if (isCreate.value) {
    if (!form.id) errors.id = t('library.form.errors.idRequired')
    else if (form.id.length > 100 || !SLUG_PATTERN.test(form.id)) errors.id = t('library.form.errors.idFormat')
  }

  return Object.keys(errors).length === 0
}

async function save(createAnother = false) {
  if (!validate()) return

  state.saving = true
  const data = {
    type: form.type,
    name: form.name.trim(),
    summary: form.summary.trim(),
    description: form.description.trim() || null,
    aliases: form.aliases,
  }

  try {
    const entry = isCreate.value
      ? await knowledgeApi.create(props.bookId, { id: form.id, ...data })
      : await knowledgeApi.update(props.bookId, props.entryId, data)

    toast.success(t(isCreate.value ? 'library.created' : 'library.saved'))
    emit('saved', entry)

    if (createAnother) {
      resetForm(form.type)
      await nextTick()
      nameField.value?.focus()
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
    errors.id = t('library.form.errors.idExists')
    return
  }
  // The API reports each faulty field; its messages are in English, so only the first one is shown as is.
  for (const [field, messages] of Object.entries(error.details?.fields ?? {})) errors[field] = messages[0]
  toast.error(errorMessage(error))
}

async function remove() {
  state.removing = true
  try {
    await knowledgeApi.remove(props.bookId, props.entryId)
    toast.success(t('library.deleted'))
    state.removalOpen = false
    emit('deleted', props.entryId)
    emit('close')
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    state.removing = false
  }
}

// The relations page, filtered on this entry. Unsaved changes would be lost on the way, so save first.
function viewRelations() {
  router.push({ name: 'relations', params: { bookId: props.bookId }, query: { character: props.entryId } })
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
    :title="isCreate ? t('library.form.createTitle') : t('library.form.editTitle')"
    @dismiss="requestClose"
  >
    <p v-if="state.loading" class="u-muted">{{ t('common.loading') }}</p>

    <form v-else id="knowledge-form" class="knowledge-form" novalidate @submit.prevent="save()">
      <div class="knowledge-form__row">
        <UiField
          ref="nameField"
          v-model="form.name"
          :label="t('library.form.name')"
          :placeholder="t('library.form.namePlaceholder')"
          :error="errors.name"
          :maxlength="255"
          :autofocus="isCreate"
          autocomplete="off"
        />
        <UiSelect v-model="form.type" :label="t('library.form.type')" :options="typeOptions" />
      </div>

      <UiField
        v-model="form.id"
        :label="t('library.form.id')"
        :hint="isCreate ? t('library.form.idHint') : t('library.form.idLocked')"
        :error="errors.id"
        :maxlength="100"
        :readonly="!isCreate"
        autocomplete="off"
        @update:model-value="onIdInput"
      />

      <UiField
        v-model="form.summary"
        multiline
        :rows="3"
        :label="t('library.form.summary')"
        :hint="t('library.form.summaryHint')"
        :error="errors.summary"
      />

      <UiField
        v-model="form.description"
        multiline
        :rows="9"
        :label="t('library.form.description')"
        :hint="t('library.form.descriptionHint')"
        :error="errors.description"
      />

      <UiTagInput
        v-model="form.aliases"
        :label="t('library.form.aliases')"
        :hint="t('library.form.aliasesHint')"
        :placeholder="t('library.form.aliasesPlaceholder')"
      />
    </form>

    <template #footer>
      <UiButton
        v-if="!isCreate"
        variant="ghost"
        :disabled="dirty"
        :title="dirty ? t('library.form.viewRelationsSave') : undefined"
        @click="viewRelations"
      >
        <Network aria-hidden="true" />
        {{ t('library.form.viewRelations') }}
      </UiButton>
      <UiButton v-if="!isCreate" variant="ghost" class="c-dialog__spacer" @click="state.removalOpen = true">
        <Trash2 aria-hidden="true" />
        {{ t('common.delete') }}
      </UiButton>
      <UiButton @click="requestClose">{{ t('common.cancel') }}</UiButton>
      <UiButton v-if="isCreate" :loading="state.saving" @click="save(true)">
        {{ t('library.form.saveAndNext') }}
      </UiButton>
      <UiButton type="submit" form="knowledge-form" variant="primary" :loading="state.saving" :disabled="state.loading">
        {{ isCreate ? t('common.create') : t('common.save') }}
      </UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.discardOpen" :title="t('library.discardTitle')" @dismiss="state.discardOpen = false">
    <p>{{ t('library.discardText') }}</p>
    <template #footer>
      <UiButton autofocus @click="state.discardOpen = false">{{ t('library.keepEditing') }}</UiButton>
      <UiButton variant="danger" @click="discard">{{ t('library.discard') }}</UiButton>
    </template>
  </UiDialog>

  <UiDialog :open="state.removalOpen" :title="t('library.deleteTitle')" @dismiss="state.removalOpen = false">
    <p>{{ t('library.deleteText', { name: form.name }) }}</p>
    <template #footer>
      <UiButton autofocus @click="state.removalOpen = false">{{ t('common.cancel') }}</UiButton>
      <UiButton variant="danger" :loading="state.removing" @click="remove">{{ t('common.delete') }}</UiButton>
    </template>
  </UiDialog>
</template>
