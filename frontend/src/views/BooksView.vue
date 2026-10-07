<script setup>
import { onMounted, reactive, ref } from 'vue'
import { BookOpen, Pencil, Plus, Trash2 } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import * as booksApi from '@/api/books'
import { useToast } from '@/composables/useToast'
import { errorMessage, t } from '@/locales'
import { formatDate } from '@/utils/format'

const toast = useToast()

const books = ref([])
const loading = ref(true)

// One dialog creates or renames a book: `id` is null when creating.
const form = reactive({ open: false, id: null, name: '', error: '', saving: false })
const removal = reactive({ open: false, book: null, saving: false })

async function load() {
  try {
    books.value = (await booksApi.list()).data
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  Object.assign(form, { open: true, id: null, name: '', error: '', saving: false })
}

function openRename(book) {
  Object.assign(form, { open: true, id: book.id, name: book.name, error: '', saving: false })
}

async function save() {
  const name = form.name.trim()
  if (!name) {
    form.error = t('common.required')
    return
  }

  form.saving = true
  form.error = ''
  try {
    if (form.id === null) {
      await booksApi.create(name)
      toast.success(t('books.created'))
    } else {
      await booksApi.rename(form.id, name)
      toast.success(t('books.renamed'))
    }
    form.open = false
    await load()
  } catch (error) {
    form.error = errorMessage(error)
  } finally {
    form.saving = false
  }
}

function askRemoval(book) {
  Object.assign(removal, { open: true, book, saving: false })
}

async function confirmRemoval() {
  removal.saving = true
  try {
    await booksApi.remove(removal.book.id)
    toast.success(t('books.deleted'))
    removal.open = false
    await load()
  } catch (error) {
    toast.error(errorMessage(error))
  } finally {
    removal.saving = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page books">
    <header class="page__header">
      <div>
        <h1 class="page__title">{{ t('books.title') }}</h1>
        <p class="page__subtitle">{{ t('books.subtitle') }}</p>
      </div>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('books.new') }}
      </UiButton>
    </header>

    <p v-if="loading" class="u-muted">{{ t('common.loading') }}</p>

    <div v-else-if="books.length === 0" class="c-empty">
      <span class="c-empty__icon"><BookOpen aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('books.empty.title') }}</h2>
      <p>{{ t('books.empty.text') }}</p>
      <UiButton variant="primary" @click="openCreate">
        <Plus aria-hidden="true" />
        {{ t('books.new') }}
      </UiButton>
    </div>

    <ul v-else class="books__grid">
      <li v-for="book in books" :key="book.id" class="c-card c-card--interactive">
        <h2 class="c-card__title">
          <RouterLink :to="{ name: 'library', params: { bookId: book.id } }">{{ book.name }}</RouterLink>
        </h2>
        <p class="c-card__meta">{{ t('books.createdOn', { date: formatDate(book.createdAt) }) }}</p>

        <div class="c-card__actions">
          <UiButton variant="ghost" size="sm" icon :aria-label="t('common.rename')" @click="openRename(book)">
            <Pencil aria-hidden="true" />
          </UiButton>
          <UiButton variant="ghost" size="sm" icon :aria-label="t('common.delete')" @click="askRemoval(book)">
            <Trash2 aria-hidden="true" />
          </UiButton>
        </div>
      </li>
    </ul>

    <UiDialog
      :open="form.open"
      :title="form.id === null ? t('books.form.createTitle') : t('books.form.renameTitle')"
      @dismiss="form.open = false"
    >
      <form id="book-form" class="books__form" novalidate @submit.prevent="save">
        <UiField
          v-model="form.name"
          :label="t('books.form.name')"
          :placeholder="t('books.form.namePlaceholder')"
          :error="form.error"
          :maxlength="255"
          autocomplete="off"
          autofocus
        />
      </form>
      <template #footer>
        <UiButton @click="form.open = false">{{ t('common.cancel') }}</UiButton>
        <UiButton type="submit" form="book-form" variant="primary" :loading="form.saving">
          {{ form.id === null ? t('common.create') : t('common.save') }}
        </UiButton>
      </template>
    </UiDialog>

    <UiDialog :open="removal.open" :title="t('books.deleteTitle')" @dismiss="removal.open = false">
      <p>{{ t('books.deleteText', { name: removal.book?.name ?? '' }) }}</p>
      <template #footer>
        <UiButton autofocus @click="removal.open = false">{{ t('common.cancel') }}</UiButton>
        <UiButton variant="danger" :loading="removal.saving" @click="confirmRemoval">
          {{ t('common.delete') }}
        </UiButton>
      </template>
    </UiDialog>
  </div>
</template>
