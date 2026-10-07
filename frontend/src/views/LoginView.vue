<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BookMarked, TriangleAlert } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import { useAuth } from '@/composables/useAuth'
import { errorMessage, t } from '@/locales'

const route = useRoute()
const router = useRouter()
const { login } = useAuth()

const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

// Only an internal path is followed after the login, never an address given in the URL.
function destination() {
  const redirect = route.query.redirect
  return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
    ? redirect
    : { name: 'books' }
}

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await login(username.value.trim(), password.value)
    await router.replace(destination())
  } catch (caught) {
    error.value = errorMessage(caught)
    password.value = ''
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="login">
    <div class="login__card">
      <header class="login__brand">
        <span class="login__logo"><BookMarked aria-hidden="true" /></span>
        <div>
          <h1>{{ t('login.title') }}</h1>
          <p>{{ t('login.subtitle') }}</p>
        </div>
      </header>

      <form class="login__form" novalidate @submit.prevent="submit">
        <p v-if="error" class="c-alert c-alert--error" role="alert">
          <TriangleAlert aria-hidden="true" />
          {{ error }}
        </p>

        <UiField v-model="username" :label="t('login.username')" autocomplete="username" required autofocus />
        <UiField
          v-model="password"
          type="password"
          :label="t('login.password')"
          autocomplete="current-password"
          required
        />

        <UiButton type="submit" variant="primary" block :loading="loading" :disabled="!username || !password">
          {{ t('login.submit') }}
        </UiButton>
      </form>
    </div>
  </main>
</template>
