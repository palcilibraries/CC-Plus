/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

import Vue from 'vue';
import VueRouter from 'vue-router';
import Vuetify from './plugins/vuetify';
import axios from 'axios';
import Form from './core/Form';

window.axios = axios;
window.Form = Form;

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i);
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default));
Vue.use(VueRouter);
Vue.component('flash', require('./components/Flash.vue').default);
Vue.component('sushi-by-inst', require('./components/SushiByInst.vue').default);
Vue.component('sushi-by-prov', require('./components/SushiByProv.vue').default);
Vue.component('provider-form', require('./components/ProviderForm.vue').default);
Vue.component('institution-form', require('./components/InstitutionForm.vue').default);

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    vuetify: Vuetify,
    el: '#app',
});
