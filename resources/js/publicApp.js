import { createApp } from 'vue';
import { createPinia } from 'pinia';
import publicRouter from './router/publicRouter';
import PublicApp from './PublicApp.vue';

const app = createApp(PublicApp);
const pinia = createPinia();

app.use(pinia);
app.use(publicRouter);
app.mount('#public-app');
