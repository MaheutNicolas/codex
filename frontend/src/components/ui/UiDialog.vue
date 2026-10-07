<script setup>
import { onMounted, ref, watch } from 'vue'
import { X } from '@lucide/vue'
import { t } from '@/locales'

const props = defineProps({
  open: Boolean,
  title: { type: String, required: true },
  side: Boolean, // a panel sliding in from the right instead of a centered dialog
})

// The dialog never closes by itself: Escape, the backdrop and the cross only ask to be dismissed,
// and the parent decides (it may want to confirm first). It closes when the `open` prop turns false.
const emit = defineEmits(['dismiss'])

const dialog = ref(null)

// The native <dialog> handles the focus trap and the backdrop.
function sync(open) {
  const element = dialog.value
  if (!element) return
  if (open && !element.open) element.showModal()
  if (!open && element.open) element.close()
}

watch(() => props.open, sync, { flush: 'post' })
onMounted(() => sync(props.open))

// A click on the dialog element itself (not on its panel) is a click on the backdrop.
function onBackdropClick(event) {
  if (event.target === dialog.value) emit('dismiss')
}
</script>

<template>
  <dialog
    ref="dialog"
    class="c-dialog"
    :class="{ 'c-dialog--drawer': side }"
    @cancel.prevent="emit('dismiss')"
    @click="onBackdropClick"
  >
    <div class="c-dialog__panel">
      <header class="c-dialog__header">
        <h2>{{ title }}</h2>
        <button
          type="button"
          class="c-button c-button--ghost c-button--icon c-button--sm"
          :aria-label="t('common.close')"
          @click="emit('dismiss')"
        >
          <X />
        </button>
      </header>

      <div class="c-dialog__body">
        <slot />
      </div>

      <footer v-if="$slots.footer" class="c-dialog__footer">
        <slot name="footer" />
      </footer>
    </div>
  </dialog>
</template>
