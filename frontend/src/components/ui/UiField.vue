<script setup>
import { computed, useId } from 'vue'

const model = defineModel({ type: String, default: '' })

const props = defineProps({
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  multiline: Boolean, // a <textarea> instead of an <input>
  rows: { type: Number, default: 4 },
  error: { type: [String, Array], default: '' },
  hint: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  autocomplete: { type: String, default: undefined },
  maxlength: { type: Number, default: undefined },
  required: Boolean,
  readonly: Boolean,
  autofocus: Boolean,
})

const id = useId()

// Lets a parent put the cursor in the field (after "save and create another", for instance).
defineExpose({ focus: () => document.getElementById(id)?.focus() })

// An API validation error is a list of messages: the first one is enough.
const errorText = computed(() => (Array.isArray(props.error) ? (props.error[0] ?? '') : props.error))
</script>

<template>
  <div class="c-field" :class="{ 'has-error': errorText }">
    <label class="c-field__label" :for="id">{{ label }}</label>

    <textarea
      v-if="multiline"
      :id="id"
      v-model="model"
      class="c-field__input c-field__input--multiline"
      :rows="rows"
      :placeholder="placeholder"
      :maxlength="maxlength"
      :required="required"
      :readonly="readonly"
      :autofocus="autofocus"
      :aria-invalid="errorText ? 'true' : undefined"
      :aria-describedby="errorText ? `${id}-error` : undefined"
    />
    <input
      v-else
      :id="id"
      v-model="model"
      class="c-field__input"
      :type="type"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :maxlength="maxlength"
      :required="required"
      :readonly="readonly"
      :autofocus="autofocus"
      :aria-invalid="errorText ? 'true' : undefined"
      :aria-describedby="errorText ? `${id}-error` : undefined"
    />

    <p v-if="hint && !errorText" class="c-field__hint">{{ hint }}</p>
    <p v-if="errorText" :id="`${id}-error`" class="c-field__error" role="alert">{{ errorText }}</p>
  </div>
</template>
