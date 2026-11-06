import { createApp } from 'vue'
import PublicApp from './public/PublicApp.vue'
import '@nextcloud/dialogs/style.css'
import './public/public.css'

const app = createApp(PublicApp)
app.mount('#helfer-public')
