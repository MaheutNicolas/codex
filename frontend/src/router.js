import { createRouter, createWebHistory } from 'vue-router'
import { setUnauthorizedHandler } from '@/api/client'
import { useAuth } from '@/composables/useAuth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      component: () => import('@/components/layout/AppShell.vue'),
      children: [
        { path: '', redirect: { name: 'books' } },
        { path: 'books', name: 'books', component: () => import('@/views/BooksView.vue') },
        {
          path: 'books/:bookId(\\d+)',
          children: [
            { path: '', redirect: (to) => ({ name: 'library', params: to.params }) },
            { path: 'library', name: 'library', component: () => import('@/views/LibraryView.vue') },
            { path: 'timeline', name: 'timeline', component: () => import('@/views/TimelineView.vue') },
            { path: 'relations', name: 'relations', component: () => import('@/views/RelationsView.vue') },
            { path: 'import', name: 'import', component: () => import('@/views/ImportView.vue') },
            { path: 'export', name: 'export', component: () => import('@/views/ExportView.vue') },
            { path: 'keys', name: 'keys', component: () => import('@/views/KeysView.vue') },
          ],
        },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

// Everything but the login page needs a logged-in user. The session is checked once, on the first navigation.
router.beforeEach(async (to) => {
  const auth = useAuth()
  if (!auth.ready.value) await auth.load()

  if (to.meta.public) {
    return auth.user.value ? { name: 'books' } : true
  }
  if (!auth.user.value) {
    return { name: 'login', query: to.fullPath === '/' ? {} : { redirect: to.fullPath } }
  }
})

// A session that expires while the app is open sends the user back to the login page.
setUnauthorizedHandler(() => {
  const { user } = useAuth()
  user.value = null
  const current = router.currentRoute.value
  if (current.name !== 'login') {
    router.replace({ name: 'login', query: { redirect: current.fullPath } })
  }
})

export default router
