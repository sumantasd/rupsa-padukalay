import { createRouter, createWebHistory } from 'vue-router';

import PublicLayout from '../views/public/PublicLayout.vue';
import HomeView from '../views/public/HomeView.vue';
import ProductListView from '../views/public/ProductListView.vue';
import ProductDetailView from '../views/public/ProductDetailView.vue';
import OffersView from '../views/public/OffersView.vue';
import StoresView from '../views/public/StoresView.vue';
import AboutView from '../views/public/AboutView.vue';
import ContactView from '../views/public/ContactView.vue';

const routes = [
    {
        path: '/',
        component: PublicLayout,
        children: [
            { path: '', name: 'public-home', component: HomeView },
            { path: 'products', name: 'public-products', component: ProductListView },
            { path: 'products/:id', name: 'public-product-detail', component: ProductDetailView },
            { path: 'categories/:category', name: 'public-category', component: ProductListView },
            { path: 'offers', name: 'public-offers', component: OffersView },
            { path: 'stores', name: 'public-stores', component: StoresView },
            { path: 'about', name: 'public-about', component: AboutView },
            { path: 'contact', name: 'public-contact', component: ContactView },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const publicRouter = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

export default publicRouter;
