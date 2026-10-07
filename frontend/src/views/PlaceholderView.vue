<script setup>
import { useRoute } from 'vue-router'
import { Hammer } from '@lucide/vue'
import { t } from '@/locales'

// The sections of a book that come with the next steps: one page for all of them.
const route = useRoute()
</script>

<template>
  <div class="page">
    <header class="page__header">
      <h1 class="page__title">{{ t(route.meta.title) }}</h1>
    </header>

    <div class="c-empty">
      <span class="c-empty__icon"><Hammer aria-hidden="true" /></span>
      <h2 class="c-empty__title">{{ t('placeholder.title') }}</h2>
      <p>{{ t('placeholder.text') }}</p>
    </div>
  </div>
</template>
