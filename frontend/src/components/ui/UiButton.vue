<script setup>
defineProps({
  variant: { type: String, default: 'secondary' }, // primary | secondary | ghost | danger
  size: { type: String, default: '' }, // '' | sm
  icon: Boolean, // square button holding only an icon
  block: Boolean,
  loading: Boolean,
  disabled: Boolean,
  type: { type: String, default: 'button' },
})
</script>

<template>
  <button
    :type="type"
    class="c-button"
    :class="[
      `c-button--${variant}`,
      size && `c-button--${size}`,
      { 'c-button--icon': icon, 'c-button--block': block },
    ]"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
  >
    <span v-if="loading" class="c-button__spinner" aria-hidden="true" />
    <slot />
  </button>
</template>
