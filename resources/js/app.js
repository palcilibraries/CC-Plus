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
Vue.component('topnav', require('./components/Navbar.vue').default);
Vue.component('flash', require('./components/Flash.vue').default);
Vue.component('user-form', require('./components/UserForm.vue').default);
Vue.component('users-by-inst', require('./components/UsersByInst.vue').default);
Vue.component('user-data-table', require('./components/UserDataTable.vue').default);
Vue.component('all-sushi-by-inst', require('./components/AllSushiByInst.vue').default);
Vue.component('all-sushi-by-prov', require('./components/AllSushiByProv.vue').default);
Vue.component('sushi-setting-form', require('./components/SushiSettingForm.vue').default);
Vue.component('provider-form', require('./components/ProviderForm.vue').default);
Vue.component('provider-data-table', require('./components/ProviderDataTable.vue').default);
Vue.component('institution-form', require('./components/InstitutionForm.vue').default);
Vue.component('institution-data-table', require('./components/InstitutionDataTable.vue').default);
Vue.component('institution-group-form', require('./components/InstitutionGroupForm.vue').default);
Vue.component('institution-types', require('./components/InstitutionTypes.vue').default);
Vue.component('institution-groups', require('./components/InstitutionGroups.vue').default);
Vue.component('harvestlog-data-table', require('./components/HarvestlogDataTable.vue').default);
Vue.component('harvestlog-summary-table', require('./components/HarvestlogSummaryTable.vue').default);
Vue.component('harvestlog-form', require('./components/HarvestlogForm.vue').default);
Vue.component('failed-harvests', require('./components/FailedHarvests.vue').default);
Vue.component('harvest-attempts', require('./components/HarvestAttempts.vue').default);
Vue.component('date-range', require('./components/DateRange.vue').default);
Vue.component('show-counter-report', require('./components/ShowCounterReport.vue').default);
Vue.component('view-reports', require('./components/ViewReports.vue').default);
Vue.component('create-report', require('./components/CreateReport.vue').default);
Vue.component('report-preview', require('./components/ReportPreview.vue').default);
Vue.component('saved-report-form', require('./components/SavedReportForm.vue').default);
Vue.component('home-saved-reports', require('./components/HomeSavedReports.vue').default);
Vue.component('manual-harvest', require('./components/ManualHarvest.vue').default);
Vue.component('alert-data-table', require('./components/AlertDataTable.vue').default);
Vue.component('alert-summary-table', require('./components/AlertSummaryTable.vue').default);
Vue.component('serveradmin-dashboard', require('./components/ServerAdminDashboard.vue').default);
Vue.component('globalsettings', require('./components/GlobalSettings.vue').default);
Vue.component('global-provider-data-table', require('./components/GlobalProviderDataTable.vue').default);
Vue.component('sushisettings-data-table', require('./components/SushiSettingsDataTable.vue').default);

/**
 * Create a fresh Vue application instance with Vuetify.
 */
const app = new Vue({
    vuetify: Vuetify,
    store,
    el: '#app',
});
