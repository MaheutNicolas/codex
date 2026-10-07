<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { Check, Monitor, Moon, Palette, Sun } from '@lucide/vue'
import { ACCENTS, MODES, useTheme } from '@/composables/useTheme'
import { t } from '@/locales'

const { state, setMode, setAccent } = useTheme()

const modeIcons = { system: Monitor, light: Sun, dark: Moon }
const open = ref(false)
const root = ref(null)

function closeOnOutsideClick(event) {
  if (open.value && root.value && !root.value.contains(event.target)) open.value = false
}

function closeOnEscape(event) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('pointerdown', closeOnOutsideClick)
  document.addEventListener('keydown', closeOnEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeOnOutsideClick)
  document.removeEventListener('keydown', closeOnEscape)
})
</script>

<template>
  <div ref="root" class="c-menu">
    <div v-if="open" class="c-menu__panel" role="group" :aria-label="t('theme.title')">
      <div class="c-menu__group">
        <p class="c-menu__title">{{ t('theme.mode') }}</p>
        <div class="c-menu__segmented">
          <button
            v-for="mode in MODES"
            :key="mode"
            type="button"
            class="c-menu__segment"
            :title="t(`theme.modes.${mode}`)"
            :aria-label="t(`theme.modes.${mode}`)"
            :aria-pressed="state.mode === mode"
            @click="setMode(mode)"
          >
            <component :is="modeIcons[mode]" aria-hidden="true" />
          </button>
        </div>
      </div>

      <div class="c-menu__group">
        <p class="c-menu__title">{{ t('theme.accent') }}</p>
        <div class="c-menu__swatches">
          <button
            v-for="accent in ACCENTS"
            :key="accent"
            type="button"
            class="c-menu__swatch"
            :data-accent="accent"
            :title="t(`theme.accents.${accent}`)"
            :aria-label="t(`theme.accents.${accent}`)"
            :aria-pressed="state.accent === accent"
            @click="setAccent(accent)"
          >
            <Check v-if="state.accent === accent" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>

    <button type="button" class="sidebar__link" :aria-expanded="open" @click="open = !open">
      <Palette aria-hidden="true" />
      {{ t('theme.title') }}
    </button>
  </div>
</template>
