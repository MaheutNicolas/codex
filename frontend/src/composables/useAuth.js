import { ref } from 'vue'
import * as authApi from '@/api/auth'

// Module-level state: every component calling useAuth() shares it, no store needed.
const user = ref(null)
const ready = ref(false)

export function useAuth() {
  /** Asks the server who is logged in. Safe to call when logged out or when the server is down. */
  async function load() {
    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function login(username, password) {
    user.value = await authApi.login(username, password)
  }

  async function logout() {
    try {
      await authApi.logout()
    } finally {
      user.value = null
    }
  }

  return { user, ready, load, login, logout }
}
