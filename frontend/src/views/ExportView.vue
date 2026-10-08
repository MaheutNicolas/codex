<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ClipboardCopy, Download } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import * as exporter from '@/api/exporter'
import { useCurrentBook } from '@/composables/useBook'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { download, fileName, toJson, toMarkdown, withoutSecrets } from '@/utils/exportDocument'

const route = useRoute()
const toast = useToast()
const { book } = useCurrentBook()

const FORMATS = {
  json: { extension: 'json', mime: 'application/json' },
  markdown: { extension: 'md', mime: 'text/markdown' },
}

const bookId = computed(() => route.params.bookId)
const data = ref(null)
const loading = ref(true)
const format = ref('markdown')
const includeSecrets = ref(true)

async function load() {
  loading.value = true
  data.value = null
  try {
    data.value = await exporter.get(bookId.value)
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

watch(bookId, load, { immediate: true })

// The file follows the options and the language of the interface: it is built again when they change.
const content = computed(() => {
  if (!data.value) return ''
  const document = includeSecrets.value ? data.value : withoutSecrets(data.value)
  return format.value === 'json' ? toJson(document) : toMarkdown(document, book.value?.name ?? 'Codex')
})

const counts = computed(() => {
  const document = includeSecrets.value ? data.value : withoutSecrets(data.value)
  return { knowledge: document.knowledge.length, events: document.events.length, links: document.participants.length }
})

const isEmpty = computed(() => data.value && !data.value.knowledge.length && !data.value.events.length)

function save() {
  const { extension, mime } = FORMATS[format.value]
  download(fileName(book.value?.name ?? '', extension), content.value, mime)
}

async function copy() {
  try {
    await navigator.clipboard.writeText(content.value)
    toast.success(t('export.copied'))
  } catch {
    toast.error(t('keys.copyFailed'))
  }
}
</script>

<template>
  <div class="page export">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('export.title') }}</h1>
        <p class="page__subtitle">{{ t('export.subtitle') }}</p>
      </div>
    </header>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <div v-else-if="isEmpty" class="c-empty">
      <h2 class="c-empty__title">{{ t('export.empty.title') }}</h2>
      <p>{{ t('export.empty.text') }}</p>
    </div>

    <section v-else-if="data" class="c-card export__card">
      <div class="library__filters" role="group" :aria-label="t('export.format')">
        <button
          v-for="(info, name) in FORMATS"
          :key="name"
          type="button"
          class="c-chip"
          :aria-pressed="format === name"
          @click="format = name"
        >
          {{ t(`export.formats.${name}.name`) }}
        </button>
      </div>
      <p class="u-muted">{{ t(`export.formats.${format}.text`) }}</p>

      <label class="c-check">
        <input v-model="includeSecrets" type="checkbox" class="c-check__input" />
        <span class="c-check__text">
          {{ t('export.secrets') }}
          <span class="c-check__hint">{{ t('export.secretsHint') }}</span>
        </span>
      </label>

      <p class="export__counts">{{ t('export.counts', counts) }}</p>

      <pre class="import__instructions export__preview" tabindex="0">{{ content }}</pre>

      <div class="export__actions">
        <UiButton variant="primary" @click="save">
          <Download aria-hidden="true" />
          {{ t('export.download') }}
        </UiButton>
        <UiButton @click="copy">
          <ClipboardCopy aria-hidden="true" />
          {{ t('keys.copy') }}
        </UiButton>
      </div>
    </section>
  </div>
</template>
