import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false,
      viewer: false,
      serveradmin: false,
      user_inst_id: 0,
      page_name: 'default',
      page_options: {
          default: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: ''},
                     datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                 groupDesc: [], multiSort: false, mustSort: false }
                   },
          users: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: '', roles:[]},
                   datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                               groupDesc: [], multiSort: false, mustSort: false }
                 },
          providers: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: ''},
                       datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                   groupDesc: [], multiSort: false, mustSort: false }
                     },
          institutions: { filters: {stat: '', groups: []},
                          datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                      groupDesc: [], multiSort: false, mustSort: false }
                        },
          institutiongroups: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: ''},
                               datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                           groupDesc: [], multiSort: false, mustSort: false }
                             },
          institutiontypes: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: ''},
                              datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                          groupDesc: [], multiSort: false, mustSort: false }
                            },
          harvestlogs: { filters: {fromYM: "", toYM: "", institutions: [], providers: [], reports: [], harv_stat: [], group: [],
                                   updated: "", source:"", codes:[]},
                         datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                     groupDesc: [], multiSort: false, mustSort: false }
                       },
          alerts: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: ''},
                               datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                           groupDesc: [], multiSort: false, mustSort: false }
                  },
          preview: { filters: {report_id: 1, fromYM: "", toYM: "", inst_id: [], institutiongroup_id: 0, plat_id: [],
                               db_id: [], prov_id: [], yop: [], datatype_id: [], accesstype_id: [], sectiontype_id: [],
                               accessmethod_id: [] },
                     datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [] }
                   },
          sushi: { filters: {inst: [], group: 0, server_prov: [], inst_prov: [], harv_stat: []},
                           datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                       groupDesc: [], multiSort: false, mustSort: false }
                 },
          globalproviders: { filters: {fromYM: "", toYM: "", inst: [], prov: [], rept: [], stat: '', refresh: ''},
                             datatable: {itemsPerPage: 10, sortBy: [], sortDesc: [], groupBy: [],
                                         groupDesc: [], multiSort: false, mustSort: false }
                           },
      },
      report_data: [],
  },
  mutations: {
    // Create the store object in local storage
    initialiseStore(state) {
      localStorage.setItem("store", JSON.stringify(state));
    },
    SET_ACCESS(state, access) {
      if (access=='ServerAdmin' || access=='SuperUser') {
          state.serveradmin=true;
          state.admin=true;
          state.manager=true;
          state.viewer=true;
      } else if (access=='Admin') {
          state.serveradmin=false;
          state.admin=true;
          state.manager=true;
          state.viewer=true;
      } else {
          state.serveradmin=false;
          state.admin=false;
          // These are independent of each other, and can both be true
          if (access=='Manager') state.manager = true;
          if (access=='Viewer')  state.viewer = true;
      }
    },
    SET_PAGENAME(state, name) {
        state.page_name = name;
    },
    SET_USERINST(state, inst_id) {
        state.user_inst_id = inst_id;
    },
    SET_ALL_FILTERS(state, filters) {
        if (state.page_name != '' && state.page_name != null)
            Object.assign(state.page_options[state.page_name].filters, filters);
    },
    SET_DATATABLE_OPTIONS(state, options) {
        if (state.page_name != '' && state.page_name != null)
           Object.assign(state.page_options[state.page_name].datatable, options);
    },
    SET_REPORTID(state, report_id) {
        state.page_options[state.page_name].filters.report_id = report_id;
    },
    SET_FROMYM(state, yearmon) {
        state.page_options[state.page_name].filters.fromYM = yearmon;
    },
    SET_TOYM(state, yearmon) {
        state.page_options[state.page_name].filters.toYM = yearmon;
    },
    SET_ACCESSMETHOD_FILTER(state, method) {
        state.page_options[state.page_name].filters.accessmethod_id = method;
    },
    SET_ACCESSTYPE_FILTER(state, type) {
        state.page_options[state.page_name].filters.accesstype_id = type;
    },
    SET_DATATYPE_FILTER(state, type) {
        state.page_options[state.page_name].filters.datatype_id = type;
    },
    SET_INSTITUTION_FILTER(state, inst) {
        state.page_options[state.page_name].filters.inst_id = inst;
    },
    SET_INSTGROUP_FILTER(state, group_id) {
        state.page_options[state.page_name].filters.institutiongroup_id = group_id;
    },
    SET_PLATFORM_FILTER(state, plat) {
        state.page_options[state.page_name].filters.plat_id = plat;
    },
    SET_PROVIDER_FILTER(state, prov) {
        state.page_options[state.page_name].filters.prov_id = prov;
    },
    SET_DATABASE_FILTER(state, dbase) {
        state.page_options[state.page_name].filters.db_id = dbase;
    },
    SET_SECTIONTYPE_FILTER(state, type) {
        state.page_options[state.page_name].filters.sectiontype_id = type;
    },
    SET_YOP(state, fromto) {
        state.page_options[state.page_name].filters.yop = fromto;
    },
    SET_REPORTDATA(state, data) {
        state.report_data = data;
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
    updateUserInst({ commit }, inst) {
      commit('SET_USERINST', inst);
    },
    updatePageName({ commit }, name) {
      commit('SET_PAGENAME', name);
    },
    updateAllFilters({ commit }, filters) {
      commit('SET_ALL_FILTERS', filters);
    },
    updateDatatableOptions({ commit }, options) {
      commit('SET_DATATABLE_OPTIONS', options);
    },
    updateReportId({ commit }, report_id) {
      commit('SET_REPORTID', report_id);
    },
    updateFromYM({ commit }, yearmon) {
      commit('SET_FROMYM', yearmon);
    },
    updateToYM({ commit }, yearmon) {
      commit('SET_TOYM', yearmon);
    },
    updateAccessMethodFilter({ commit }, method) {
      commit('SET_ACCESSMETHOD_FILTER', method);
    },
    updateAccessTypeFilter({ commit }, type) {
      commit('SET_ACCESSTYPE_FILTER', type);
    },
    updateDataTypeFilter({ commit }, type) {
      commit('SET_DATATYPE_FILTER', type);
    },
    updateInstitutionFilter({ commit }, inst) {
      commit('SET_INSTITUTION_FILTER', inst);
    },
    updateInstGroupFilter({ commit }, group_id) {
      commit('SET_INSTGROUP_FILTER', group_id);
    },
    updatePlatformFilter({ commit }, plat) {
      commit('SET_PLATFORM_FILTER', plat);
    },
    updateProviderFilter({ commit }, prov) {
      commit('SET_PROVIDER_FILTER', prov);
    },
    updateDataBaseFilter({ commit }, prov) {
      commit('SET_DATABASE_FILTER', prov);
    },
    updateSectionTypeFilter({ commit }, type) {
      commit('SET_SECTIONTYPE_FILTER', type);
    },
    updateYopFilter({ commit }, fromto) {
      commit('SET_YOP', fromto);
    },
    updateReportData({ commit }, data) {
      commit('SET_REPORTDATA', data);
    },
  },
  getters: {
    is_serveradmin: state => { return state.serveradmin },
    is_admin: state => { return state.admin },
    is_manager: state => { return state.manager },
    is_viewer: state => { return state.viewer },
    user_inst_id: state => { return state.user_inst_id },
    page_name: (state) => {  return state.page_name },
    all_filters: state => {
        return (state.page_name != '' && state.page_name != null) ?
          state.page_options[state.page_name].filters : [];
    },
    datatable_options: (state) => {
        return (state.page_name != '' && state.page_name != null) ?
          state.page_options[state.page_name].datatable : [];
    },
    filter_by_fromYM: state => {
        return (state.page_name != '' && state.page_name != null) ?
          state.page_options[state.page_name].filters.fromYM : null;
    },
    filter_by_toYM: state => {
        return (state.page_name != '' && state.page_name != null) ?
          state.page_options[state.page_name].filters.toYM : null;
    },
    report_data: state => {
      return state.report_data
    },
  },
});
