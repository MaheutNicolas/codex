import { ref } from 'vue'

const toasts = ref([])
let nextId = 1

export function useToast() {
  function dismiss(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }

  function notify(message, type = 'info', duration = 4500) {
    const id = nextId++
    toasts.value.push({ id, message, type })
    if (duration > 0) setTimeout(() => dismiss(id), duration)
  }

  return {
    toasts,
    dismiss,
    notify,
    success: (message) => notify(message, 'success'),
    error: (message) => notify(message, 'error', 8000),
  }
}
