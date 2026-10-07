<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Menu } from '@lucide/vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import { t } from '@/locales'

const route = useRoute()
// On small screens the sidebar is a drawer, closed again after each navigation.
const drawerOpen = ref(false)

watch(() => route.fullPath, () => (drawerOpen.value = false))
</script>

<template>
  <div class="app-shell">
    <AppSidebar :open="drawerOpen" />
    <div class="app-shell__scrim" :class="{ 'is-visible': drawerOpen }" @click="drawerOpen = false" />

    <div class="app-shell__main">
      <header class="app-shell__topbar">
        <button
          type="button"
          class="c-button c-button--ghost c-button--icon"
          :aria-label="t('nav.openMenu')"
          @click="drawerOpen = true"
        >
          <Menu />
        </button>
        {{ t('app.name') }}
      </header>

      <main>
        <RouterView />
      </main>
    </div>
  </div>
</template>
