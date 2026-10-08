<script setup>
import { computed } from 'vue'
import { Pencil, X } from '@lucide/vue'
import UiButton from '@/components/ui/UiButton.vue'
import { SECTIONS } from '@/utils/importDocument'
import { t } from '@/locales'

// The items to review, grouped by section. Each row is { item, title, ids, status, errors } built by the page.
const props = defineProps({
  rows: { type: Array, required: true },
  focusUid: { type: Number, default: null }, // the row the server complained about
})

const emit = defineEmits(['toggle', 'edit', 'remove'])

const groups = computed(() =>
  SECTIONS.map((section) => ({ section, rows: props.rows.filter((row) => row.item.section === section) })).filter(
    (group) => group.rows.length > 0,
  ),
)
</script>

<template>
  <div class="import-review">
    <section v-for="group in groups" :key="group.section" class="import-review__group">
      <h3 class="import-review__title">
        {{ t(`import.sections.${group.section}`) }}
        <span class="c-chip__count">{{ group.rows.length }}</span>
      </h3>

      <ul class="import-review__list">
        <li
          v-for="row in group.rows"
          :key="row.item.uid"
          class="import-row"
          :class="{
            'is-skipped': !row.item.included,
            'has-error': row.item.included && Object.keys(row.errors).length > 0,
            'is-focused': row.item.uid === focusUid,
          }"
        >
          <input
            type="checkbox"
            class="c-check__input import-row__check"
            :checked="row.item.included"
            :aria-label="t('import.review.include', { title: row.title })"
            @change="emit('toggle', row.item.uid)"
          />

          <div class="import-row__body">
            <div class="import-row__title-line">
              <span class="import-row__title">{{ row.title }}</span>
              <span v-if="row.ids" class="import-row__ids">{{ row.ids }}</span>
              <span v-if="row.status" class="c-badge" :class="{ 'c-badge--accent': row.item.included }">
                {{ row.status }}
              </span>
            </div>
            <p v-if="row.detail" class="import-row__detail">{{ row.detail }}</p>
            <ul v-if="row.item.included && Object.keys(row.errors).length > 0" class="import-row__errors">
              <li v-for="(message, field) in row.errors" :key="field">
                <code>{{ field }}</code> {{ message }}
              </li>
            </ul>
          </div>

          <div class="import-row__actions">
            <UiButton
              variant="ghost"
              size="sm"
              icon
              :aria-label="t('import.review.edit', { title: row.title })"
              @click="emit('edit', row.item.uid)"
            >
              <Pencil aria-hidden="true" />
            </UiButton>
            <UiButton
              variant="ghost"
              size="sm"
              icon
              :aria-label="t('import.review.remove', { title: row.title })"
              @click="emit('remove', row.item.uid)"
            >
              <X aria-hidden="true" />
            </UiButton>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
