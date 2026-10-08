<script setup>
import { computed, reactive, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiSelect from '@/components/ui/UiSelect.vue'
import UiTagInput from '@/components/ui/UiTagInput.vue'
import { KNOWLEDGE_TYPES, RELATION_TYPES } from '@/constants'
import { t } from '@/locales'

const props = defineProps({
  item: { type: Object, default: null }, // { section, data } being edited, null when the panel is closed
  check: { type: Function, required: true }, // (item) => { field: message }, the same checks as the review
})

// `apply` carries the corrected data of the item.
const emit = defineEmits(['close', 'apply'])

const typeOptions = computed(() => KNOWLEDGE_TYPES.map(({ value }) => ({ value, label: t(`library.types.${value}`) })))
const relationTypeOptions = computed(() =>
  RELATION_TYPES.map(({ value }) => ({ value, label: t(`relations.types.${value}`) })),
)

const form = reactive({})
const errors = reactive({})

const text = (value) => (value === null || value === undefined ? '' : String(value))
const list = (value) => (Array.isArray(value) ? value.filter((entry) => typeof entry === 'string') : [])

// The form starts from the item as it is, even when a value has the wrong type: the checks then point it out.
watch(
  () => props.item,
  (item) => {
    Object.keys(errors).forEach((field) => delete errors[field])
    Object.keys(form).forEach((field) => delete form[field])
    if (!item) return

    const d = item.data
    if (item.section === 'knowledge') {
      Object.assign(form, {
        id: text(d.id),
        type: text(d.type) || KNOWLEDGE_TYPES[0].value,
        name: text(d.name),
        summary: text(d.summary),
        description: text(d.description),
        aliases: list(d.aliases),
      })
    } else if (item.section === 'events') {
      Object.assign(form, {
        id: text(d.id),
        title: text(d.title),
        summary: text(d.summary),
        detail: text(d.detail),
        worldOrder: text(d.worldOrder),
        worldDate: text(d.worldDate),
        chapter: text(d.chapter),
        revealed: d.revealed !== false,
        tags: list(d.tags),
      })
    } else if (item.section === 'relations') {
      Object.assign(form, {
        id: text(d.id),
        sourceId: text(d.sourceId),
        targetId: text(d.targetId),
        type: text(d.type) || RELATION_TYPES[0].value,
        chapter: text(d.chapter),
        revealed: d.revealed !== false,
        note: text(d.note),
      })
    } else {
      Object.assign(form, { eventId: text(d.eventId), knowledgeId: text(d.knowledgeId), role: text(d.role) })
    }
  },
  { immediate: true },
)

const orNull = (value) => value.trim() || null
// A number is sent as a number; anything else stays as typed so that the checks reject it.
const asNumber = (value) => (/^-?\d+$/.test(value.trim()) ? Number(value.trim()) : value.trim())

function build() {
  if (props.item.section === 'knowledge') {
    return {
      id: form.id.trim(),
      type: form.type,
      name: form.name.trim(),
      summary: form.summary.trim(),
      description: orNull(form.description),
      aliases: form.aliases,
    }
  }
  if (props.item.section === 'events') {
    return {
      id: form.id.trim(),
      title: form.title.trim(),
      summary: form.summary.trim(),
      detail: orNull(form.detail),
      worldOrder: asNumber(form.worldOrder),
      worldDate: orNull(form.worldDate),
      chapter: form.chapter.trim() ? asNumber(form.chapter) : null,
      revealed: form.revealed,
      tags: form.tags,
    }
  }
  if (props.item.section === 'relations') {
    return {
      id: form.id.trim(),
      sourceId: form.sourceId.trim(),
      targetId: form.targetId.trim(),
      type: form.type,
      chapter: form.chapter.trim() ? asNumber(form.chapter) : null,
      revealed: form.revealed,
      note: orNull(form.note),
    }
  }
  return { eventId: form.eventId.trim(), knowledgeId: form.knowledgeId.trim(), role: orNull(form.role) }
}

function apply() {
  const data = build()
  const found = props.check({ ...props.item, data })
  Object.keys(errors).forEach((field) => delete errors[field])
  Object.assign(errors, found)
  if (Object.keys(found).length === 0) emit('apply', data)
}

// An error goes away as soon as its field is edited.
function clear(field) {
  delete errors[field]
}
</script>

<template>
  <UiDialog :open="item !== null" side :title="t('import.edit.title')" @dismiss="emit('close')">
    <form v-if="item" id="import-item-form" class="event-form" novalidate @submit.prevent="apply">
      <template v-if="item.section === 'knowledge'">
        <div class="knowledge-form__row">
          <UiField
            v-model="form.name"
            :label="t('library.form.name')"
            :error="errors.name"
            @update:model-value="clear('name')"
          />
          <UiSelect v-model="form.type" :label="t('library.form.type')" :options="typeOptions" :error="errors.type" />
        </div>
        <UiField v-model="form.id" :label="t('library.form.id')" :error="errors.id" @update:model-value="clear('id')" />
        <UiField
          v-model="form.summary"
          multiline
          :rows="3"
          :label="t('library.form.summary')"
          :error="errors.summary"
          @update:model-value="clear('summary')"
        />
        <UiField
          v-model="form.description"
          multiline
          :rows="8"
          :label="t('library.form.description')"
          :error="errors.description"
        />
        <UiTagInput
          v-model="form.aliases"
          :label="t('library.form.aliases')"
          :hint="t('library.form.aliasesHint')"
          :placeholder="t('library.form.aliasesPlaceholder')"
        />
      </template>

      <template v-else-if="item.section === 'events'">
        <UiField
          v-model="form.title"
          :label="t('timeline.form.title')"
          :error="errors.title"
          @update:model-value="clear('title')"
        />
        <UiField
          v-model="form.id"
          :label="t('timeline.form.id')"
          :error="errors.id"
          @update:model-value="clear('id')"
        />
        <UiField
          v-model="form.summary"
          multiline
          :rows="3"
          :label="t('timeline.form.summary')"
          :error="errors.summary"
          @update:model-value="clear('summary')"
        />
        <div class="event-form__row">
          <UiField
            v-model="form.worldOrder"
            :label="t('timeline.form.worldOrder')"
            :error="errors.worldOrder"
            @update:model-value="clear('worldOrder')"
          />
          <UiField
            v-model="form.chapter"
            :label="t('timeline.form.chapter')"
            :hint="t('timeline.form.chapterHint')"
            :error="errors.chapter"
            @update:model-value="clear('chapter')"
          />
        </div>
        <UiField v-model="form.worldDate" :label="t('timeline.form.worldDate')" :error="errors.worldDate" />
        <label class="c-check">
          <input v-model="form.revealed" type="checkbox" class="c-check__input" />
          <span class="c-check__text">{{ t('timeline.form.revealed') }}</span>
        </label>
        <UiField
          v-model="form.detail"
          multiline
          :rows="6"
          :label="t('timeline.form.detail')"
          :error="errors.detail"
        />
        <UiTagInput
          v-model="form.tags"
          :label="t('timeline.form.tags')"
          :hint="t('timeline.form.tagsHint')"
          :placeholder="t('timeline.form.tagsPlaceholder')"
        />
      </template>

      <template v-else-if="item.section === 'relations'">
        <UiField
          v-model="form.sourceId"
          :label="t('relations.form.source')"
          :hint="t('import.edit.knowledgeIdHint')"
          :error="errors.sourceId"
          @update:model-value="clear('sourceId')"
        />
        <UiField
          v-model="form.targetId"
          :label="t('relations.form.target')"
          :hint="t('import.edit.knowledgeIdHint')"
          :error="errors.targetId"
          @update:model-value="clear('targetId')"
        />
        <UiSelect v-model="form.type" :label="t('relations.form.type')" :options="relationTypeOptions" :error="errors.type" />
        <UiField
          v-model="form.chapter"
          :label="t('relations.form.chapter')"
          :hint="t('relations.form.chapterHint')"
          :error="errors.chapter"
          @update:model-value="clear('chapter')"
        />
        <label class="c-check">
          <input v-model="form.revealed" type="checkbox" class="c-check__input" />
          <span class="c-check__text">{{ t('relations.form.revealed') }}</span>
        </label>
        <UiField v-model="form.note" :label="t('relations.form.note')" :error="errors.note" />
        <UiField
          v-model="form.id"
          :label="t('relations.form.id')"
          :error="errors.id"
          @update:model-value="clear('id')"
        />
      </template>

      <template v-else>
        <UiField
          v-model="form.eventId"
          :label="t('import.edit.eventId')"
          :hint="t('import.edit.eventIdHint')"
          :error="errors.eventId"
          @update:model-value="clear('eventId')"
        />
        <UiField
          v-model="form.knowledgeId"
          :label="t('import.edit.knowledgeId')"
          :hint="t('import.edit.knowledgeIdHint')"
          :error="errors.knowledgeId"
          @update:model-value="clear('knowledgeId')"
        />
        <UiField
          v-model="form.role"
          :label="t('timeline.form.roleLabel')"
          :placeholder="t('timeline.form.rolePlaceholder')"
          :error="errors.role"
        />
      </template>
    </form>

    <template #footer>
      <UiButton @click="emit('close')">{{ t('common.cancel') }}</UiButton>
      <UiButton type="submit" form="import-item-form" variant="primary">{{ t('import.edit.apply') }}</UiButton>
    </template>
  </UiDialog>
</template>
