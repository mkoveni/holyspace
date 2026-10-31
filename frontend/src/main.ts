/**
 * main.ts
 *
 * Bootstraps Vuetify and other plugins then mounts the App`
 */

// Composables
import { createApp } from 'vue'

import PeopleSearch from '@/components/common/PeopleSearch.vue'
// Plugins
import { registerPlugins } from '@/plugins'
// Components
import App from './App.vue'

// Styles
import '@fontsource/manrope/400.css'
import '@fontsource/manrope/500.css'
import '@fontsource/manrope/600.css'
import '@fontsource/manrope/700.css'
import '@fontsource/manrope/800.css'
import '@fontsource/plus-jakarta-sans/400.css'
import '@fontsource/plus-jakarta-sans/500.css'
import '@fontsource/plus-jakarta-sans/600.css'
import '@fontsource/plus-jakarta-sans/700.css'
import '@fontsource/plus-jakarta-sans/800.css'
import '@/styles/main.scss'
import '@/directory/directory.scss'
import '@/children/children.scss'

const app = createApp(App)

registerPlugins(app)
app.component('PeopleSearch', PeopleSearch)

app.mount('#app')
