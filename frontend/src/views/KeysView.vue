<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Copy, RefreshCw } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import * as apiKeyApi from '@/api/apiKey'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { formatDate } from '@/utils/format'

const route = useRoute()
const toast = useToast()

const bookId = computed(() => route.params.bookId)
const key = ref(null)
const loading = ref(true)
const regenerating = ref(false)
const confirmOpen = ref(false)

watch(
  bookId,
  async () => {
    loading.value = true
    key.value = null
    try {
      key.value = await apiKeyApi.get(bookId.value)
    } catch (error) {
      toast.error(errorMessage(error))
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

// The address an AI connects to: the key is part of it.
const mcpUrl = computed(() => (key.value ? `${window.location.origin}/mcp/${key.value.token}` : ''))

async function copy(text = key.value.token, message = 'keys.copied') {
  try {
    await navigator.clipboard.writeText(text)
    toast.success(t(message))
  } catch {
    toast.error(t('keys.copyFailed'))
  }
}

async function regenerate() {
  regenerating.value = true
  try {
    key.value = await apiKeyApi.regenerate(bookId.value)
    confirmOpen.value = false
    toast.success(t('keys.regenerated'))
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    regenerating.value = false
  }
}
</script>

<template>
  <div class="page">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('keys.title') }}</h1>
        <p class="page__subtitle">{{ t('keys.subtitle') }}</p>
      </div>
    </header>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <section v-else-if="key" class="c-card key-card" aria-labelledby="key-title">
      <h2 id="key-title" class="c-card__title">{{ t('keys.cardTitle') }}</h2>
      <p class="u-muted">{{ t('keys.cardText') }}</p>

      <div class="key-card__token">
        <input
          class="c-field__input key-card__input"
          type="text"
          readonly
          :value="key.token"
          :aria-label="t('keys.tokenLabel')"
          @focus="$event.target.select()"
        />
        <UiButton @click="copy()">
          <Copy aria-hidden="true" />
          {{ t('keys.copy') }}
        </UiButton>
      </div>

      <p class="key-card__meta">
        {{ t('keys.createdOn', { date: formatDate(key.createdAt) }) }}
        ·
        {{ key.lastUsedAt ? t('keys.lastUsed', { date: formatDate(key.lastUsedAt) }) : t('keys.neverUsed') }}
      </p>

      <div class="key-card__actions">
        <UiButton variant="danger" @click="confirmOpen = true">
          <RefreshCw aria-hidden="true" />
          {{ t('keys.regenerate') }}
        </UiButton>
        <p class="c-field__hint">{{ t('keys.regenerateHint') }}</p>
      </div>
    </section>

    <section v-if="key" class="c-card key-card key-card--spaced" aria-labelledby="mcp-title">
      <h2 id="mcp-title" class="c-card__title">{{ t('keys.mcp.title') }}</h2>
      <p class="u-muted">{{ t('keys.mcp.text') }}</p>

      <div class="key-card__token">
        <input
          class="c-field__input key-card__input"
          type="text"
          readonly
          :value="mcpUrl"
          :aria-label="t('keys.mcp.label')"
          @focus="$event.target.select()"
        />
        <UiButton @click="copy(mcpUrl, 'keys.mcp.copied')">
          <Copy aria-hidden="true" />
          {{ t('keys.copy') }}
        </UiButton>
      </div>

      <p class="c-field__hint">{{ t('keys.mcp.hint') }}</p>
    </section>

    <UiDialog :open="confirmOpen" :title="t('keys.confirmTitle')" @dismiss="confirmOpen = false">
      <p>{{ t('keys.confirmText') }}</p>
      <template #footer>
        <UiButton autofocus @click="confirmOpen = false">{{ t('common.cancel') }}</UiButton>
        <UiButton variant="danger" :loading="regenerating" @click="regenerate">{{ t('keys.regenerate') }}</UiButton>
      </template>
    </UiDialog>
  </div>
</template>
