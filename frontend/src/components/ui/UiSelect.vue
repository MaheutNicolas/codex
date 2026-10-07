<script setup>
import { computed, useId } from 'vue'
import { ChevronDown } from '@lucide/vue'

const model = defineModel({ type: String, default: '' })

const props = defineProps({
  label: { type: String, required: true },
  options: { type: Array, required: true }, // [{ value, label }]
  error: { type: [String, Array], default: '' },
  hint: { type: String, default: '' },
})

const id = useId()
const errorText = computed(() => (Array.isArray(props.error) ? (props.error[0] ?? '') : props.error))
</script>

<template>
  <div class="c-field" :class="{ 'has-error': errorText }">
    <label class="c-field__label" :for="id">{{ label }}</label>

    <div class="c-select">
      <select
        :id="id"
        v-model="model"
        class="c-field__input c-select__control"
        :aria-invalid="errorText ? 'true' : undefined"
        :aria-describedby="errorText ? `${id}-error` : undefined"
      >
        <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <ChevronDown class="c-select__icon" aria-hidden="true" />
    </div>

    <p v-if="hint && !errorText" class="c-field__hint">{{ hint }}</p>
    <p v-if="errorText" :id="`${id}-error`" class="c-field__error" role="alert">{{ errorText }}</p>
  </div>
</template>
