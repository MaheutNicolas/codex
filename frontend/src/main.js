import '@fontsource-variable/inter'
import '@/styles/main.scss'

import { createApp } from 'vue'
import App from '@/App.vue'
import router from '@/router'
import { useTheme } from '@/composables/useTheme'

useTheme().apply()

createApp(App).use(router).mount('#app')
