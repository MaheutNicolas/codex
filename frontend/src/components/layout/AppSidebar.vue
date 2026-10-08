<script setup>
import { useRouter } from 'vue-router'
import { BookMarked, BookOpen, Clock, KeyRound, Library, LogOut, Upload } from '@lucide/vue'
import ThemeMenu from '@/components/layout/ThemeMenu.vue'
import { useAuth } from '@/composables/useAuth'
import { useBookLoader } from '@/composables/useBook'
import { t } from '@/locales'

defineProps({ open: Boolean })

const router = useRouter()
const { user, logout } = useAuth()
// The sidebar is the one place that loads the book named by the route.
const { book } = useBookLoader()

const bookLinks = [
  { name: 'library', label: 'nav.library', icon: Library },
  { name: 'timeline', label: 'nav.timeline', icon: Clock },
  { name: 'import', label: 'nav.import', icon: Upload },
  { name: 'keys', label: 'nav.keys', icon: KeyRound },
]

async function onLogout() {
  await logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <aside class="sidebar" :class="{ 'is-open': open }">
    <RouterLink class="sidebar__brand" :to="{ name: 'books' }">
      <span class="sidebar__logo"><BookMarked aria-hidden="true" /></span>
      {{ t('app.name') }}
    </RouterLink>

    <nav class="sidebar__nav">
      <RouterLink class="sidebar__link" active-class="" exact-active-class="is-active" :to="{ name: 'books' }">
        <BookOpen aria-hidden="true" />
        {{ t('nav.books') }}
      </RouterLink>
    </nav>

    <section v-if="book" class="sidebar__book">
      <p class="sidebar__label">{{ t('nav.currentBook') }}</p>
      <p class="sidebar__book-name">{{ book.name }}</p>
      <nav class="sidebar__nav">
        <RouterLink
          v-for="link in bookLinks"
          :key="link.name"
          class="sidebar__link"
          active-class=""
          exact-active-class="is-active"
          :to="{ name: link.name, params: { bookId: book.id } }"
        >
          <component :is="link.icon" aria-hidden="true" />
          {{ t(link.label) }}
        </RouterLink>
      </nav>
    </section>

    <div class="sidebar__footer">
      <ThemeMenu />
      <div v-if="user" class="sidebar__user">
        <span class="sidebar__avatar" aria-hidden="true">{{ user.username.charAt(0) }}</span>
        <span class="sidebar__username">{{ user.username }}</span>
      </div>
      <button type="button" class="sidebar__link" @click="onLogout">
        <LogOut aria-hidden="true" />
        {{ t('nav.logout') }}
      </button>
    </div>
  </aside>
</template>
