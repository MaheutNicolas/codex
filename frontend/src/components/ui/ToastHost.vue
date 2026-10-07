<script setup>
import { CircleCheck, Info, TriangleAlert, X } from '@lucide/vue'
import { useToast } from '@/composables/useToast'
import { t } from '@/locales'

const { toasts, dismiss } = useToast()

const icons = { success: CircleCheck, error: TriangleAlert, info: Info }
</script>

<template>
  <div class="c-toasts" aria-live="polite">
    <div v-for="toast in toasts" :key="toast.id" class="c-toast" :class="`c-toast--${toast.type}`">
      <component :is="icons[toast.type]" class="c-toast__icon" aria-hidden="true" />
      <p class="c-toast__message">{{ toast.message }}</p>
      <button
        type="button"
        class="c-button c-button--ghost c-button--icon c-button--sm"
        :aria-label="t('common.close')"
        @click="dismiss(toast.id)"
      >
        <X />
      </button>
    </div>
  </div>
</template>
