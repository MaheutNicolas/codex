import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as booksApi from '@/api/books'
import { errorMessage } from '@/locales'
import { useToast } from '@/composables/useToast'

const book = ref(null)

/**
 * The book named by the :bookId of the current route. Call it once, from the layout (the sidebar):
 * it watches the route and keeps `book` up to date. Other components just read useCurrentBook().
 */
export function useBookLoader() {
  const route = useRoute()
  const router = useRouter()
  const toast = useToast()

  watch(
    () => route.params.bookId,
    async (id) => {
      if (!id) {
        book.value = null
        return
      }
      try {
        book.value = await booksApi.get(id)
      } catch (error) {
        book.value = null
        if (error.code === 'BOOK_NOT_FOUND') {
          toast.error(errorMessage(error))
          router.replace({ name: 'books' })
        }
      }
    },
    { immediate: true },
  )

  return { book }
}

export function useCurrentBook() {
  return { book }
}
