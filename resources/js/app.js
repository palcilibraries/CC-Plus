/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */
require('./bootstrap');

import Vue from 'vue';

// Plugins and state store
import Vuetify from '@/js/plugins/vuetify';
import { store } from '@/js/plugins/store.js';

/**
 * The following block of code may be used to recursively scan for
 * Vue components and automatically register them.
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */
// const files = require.context('./', true, /\.vue$/i);
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default));
Vue.component('topnav', require('./components/Navbar.vue').default);
Vue.component('flash', require('./components/Flash.vue').default);
Vue.component('user-form', require('./components/UserForm.vue').default);
Vue.component('users-by-inst', require('./components/UsersByInst.vue').default);
Vue.component('user-data-table', require('./components/UserDataTable.vue').default);
Vue.component('sushi-by-inst', require('./components/SushiByInst.vue').default);
Vue.component('sushi-by-prov', require('./components/SushiByProv.vue').default);
Vue.component('provider-form', require('./components/ProviderForm.vue').default);
Vue.component('provider-data-table', require('./components/ProviderDataTable.vue').default);
Vue.component('institution-form', require('./components/InstitutionForm.vue').default);
Vue.component('institution-data-table', require('./components/InstitutionDataTable.vue').default);

/**
 * Create a fresh Vue application instance with Vuetify.
 */
const app = new Vue({
    vuetify: Vuetify,
    store,
    el: '#app',
});
