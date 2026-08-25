import { createApp } from 'vue'
import { t, n } from '@nextcloud/l10n'

import App from './App.vue'
import router from './router/index.js'

const app = createApp(App)
app.mixin({ methods: { t, n } })
app.use(router)
app.mount('#projectmanager')
