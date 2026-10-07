<script setup>
import { ref, useId } from 'vue'
import { X } from '@lucide/vue'
import { t } from '@/locales'

// A list of short texts typed one by one: Enter or a comma adds one, Backspace removes the last.
const tags = defineModel({ type: Array, default: () => [] })

defineProps({
  label: { type: String, required: true },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  maxlength: { type: Number, default: 100 },
  removeLabel: { type: String, default: 'library.form.removeAlias' }, // locale key, receives {alias}
})

const id = useId()
const input = ref(null)
const draft = ref('')

function commit() {
  const value = draft.value.trim()
  draft.value = ''
  if (!value) return
  // The same text twice (whatever the case) is not worth a second tag.
  if (tags.value.some((tag) => tag.toLowerCase() === value.toLowerCase())) return
  tags.value = [...tags.value, value]
}

function onKeydown(event) {
  if (event.key === ',' || (event.key === 'Enter' && draft.value.trim() !== '')) {
    event.preventDefault()
    commit()
  } else if (event.key === 'Backspace' && draft.value === '' && tags.value.length > 0) {
    tags.value = tags.value.slice(0, -1)
  }
}

function remove(index) {
  tags.value = tags.value.filter((_, position) => position !== index)
  input.value?.focus()
}
</script>

<template>
  <div class="c-field">
    <label class="c-field__label" :for="id">{{ label }}</label>

    <div class="c-tags" @click="input.focus()">
      <span v-for="(tag, index) in tags" :key="tag" class="c-tags__chip">
        {{ tag }}
        <button
          type="button"
          class="c-tags__remove"
          :aria-label="t(removeLabel, { alias: tag })"
          @click.stop="remove(index)"
        >
          <X aria-hidden="true" />
        </button>
      </span>
      <input
        :id="id"
        ref="input"
        v-model="draft"
        class="c-tags__input"
        :placeholder="tags.length === 0 ? placeholder : ''"
        :maxlength="maxlength"
        autocomplete="off"
        @keydown="onKeydown"
        @blur="commit"
      />
    </div>

    <p v-if="hint" class="c-field__hint">{{ hint }}</p>
  </div>
</template>
