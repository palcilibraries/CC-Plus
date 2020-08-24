import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false,
      viewer: false,
      user_inst_id: 0,
      filter_by: {
          report_id: 1,
          fromYM: "",
          toYM: "",
          inst_id: [],
          institutiongroup_id: 0,
          plat_id: [],
          prov_id: [],
          datatype_id: [],
          accesstype_id: [],
          sectiontype_id: [],
          accessmethod_id: [],
          yop: [],
      },
      options: {
          accessmethods: [],
          accesstypes: [],
          datatypes: [],
          sectiontypes: [],
          institutions: [],
          institutiongroups: [],
          providers: [],
          platforms: []
      },
      report_data: [],
  },
  mutations: {
    SET_ACCESS(state, access) {
      if (access=='Admin') {
          state.admin=true;
          state.manager=true;
      } else if (access=='Manager') {
          state.admin=false;
          state.manager=true;
      } else if (access=='Viewer') {
          state.viewer=true;
      } else {
          state.admin=false;
          state.manager=false;
          state.viewer=false;
      }
    },
    SET_USERINST(state, inst_id) {
        state.user_inst_id = inst_id;
    },
    SET_REPORTID(state, report_id) {
        state.filter_by.report_id = report_id;
    },
    SET_REPORTDATA(state, data) {
        state.report_data = data;
    },
    SET_ALL_FILTERS(state, filter_by) {
        state.filter_by = filter_by;
    },
    SET_FROMYM(state, yearmon) {
        state.filter_by.fromYM = yearmon;
    },
    SET_TOYM(state, yearmon) {
        state.filter_by.toYM = yearmon;
    },
    SET_ACCESSMETHOD_FILTER(state, method) {
        state.filter_by.accessmethod_id = method;
    },
    SET_ACCESSTYPE_FILTER(state, type) {
        state.filter_by.accesstype_id = type;
    },
    SET_DATATYPE_FILTER(state, type) {
        state.filter_by.datatype_id = type;
    },
    SET_INSTITUTION_FILTER(state, inst) {
        state.filter_by.inst_id = inst;
    },
    SET_INSTGROUP_FILTER(state, group_id) {
        state.filter_by.institutiongroup_id = group_id;
    },
    SET_PLATFORM_FILTER(state, plat) {
        state.filter_by.plat_id = plat;
    },
    SET_PROVIDER_FILTER(state, prov) {
        state.filter_by.prov_id = prov;
    },
    SET_SECTIONTYPE_FILTER(state, type) {
        state.filter_by.sectiontype_id = type;
    },
    SET_YOP(state, fromto) {
        state.filter_by.yop = fromto;
    },
    SET_ACCESSMETHOD_OPTIONS(state, options) {
        state.options.accessmethods = options;
    },
    SET_ACCESSTYPE_OPTIONS(state, options) {
        state.options.accesstypes = options;
    },
    SET_DATATYPE_OPTIONS(state, options) {
        state.options.datatypes = options;
    },
    SET_INSTITUTION_OPTIONS(state, options) {
        state.options.institutions = options;
    },
    SET_INSTGROUP_OPTIONS(state, options) {
        state.options.institutiongroups = options;
    },
    SET_PLATFORM_OPTIONS(state, options) {
        state.options.platforms = options;
    },
    SET_PROVIDER_OPTIONS(state, options) {
        state.options.providers = options;
    },
    SET_SECTIONTYPE_OPTIONS(state, options) {
        state.options.sectiontypes = options;
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
    updateUserInst({ commit }, inst) {
      commit('SET_USERINST', inst);
    },
    updateReportId({ commit }, report_id) {
      commit('SET_REPORTID', report_id);
    },
    updateReportData({ commit }, data) {
      commit('SET_REPORTDATA', data);
    },
    updateFromYM({ commit }, yearmon) {
      commit('SET_FROMYM', yearmon);
    },
    updateToYM({ commit }, yearmon) {
      commit('SET_TOYM', yearmon);
    },
    updateAllFilters({ commit }, filter_by) {
      commit('SET_ALL_FILTERS', filter_by);
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
    updateSectionTypeFilter({ commit }, type) {
      commit('SET_SECTIONTYPE_FILTER', type);
    },
    updateYopFilter({ commit }, fromto) {
      commit('SET_YOP', fromto);
    },
    updateAccessMethodOptions({ commit }, methods) {
      commit('SET_ACCESSMETHOD_OPTIONS', methods);
    },
    updateAccessTypeOptions({ commit }, types) {
      commit('SET_ACCESSTYPE_OPTIONS', types);
    },
    updateDataTypeOptions({ commit }, types) {
      commit('SET_DATATYPE_OPTIONS', types);
    },
    updateInstitutionOptions({ commit }, insts) {
      commit('SET_INSTITUTION_OPTIONS', insts);
    },
    updateInstGroupOptions({ commit }, groups) {
      commit('SET_INSTGROUP_OPTIONS', groups);
    },
    updatePlatformOptions({ commit }, plats) {
      commit('SET_PLATFORM_OPTIONS', plats);
    },
    updateProviderOptions({ commit }, provs) {
      commit('SET_PROVIDER_OPTIONS', provs);
    },
    updateSectionTypeOptions({ commit }, types) {
      commit('SET_SECTIONTYPE_OPTIONS', types);
    },
  },
  getters: {
    is_admin: state => {
      return state.admin
    },
    is_manager: state => {
      return state.manager
    },
    is_viewer: state => {
      return state.viewer
    },
    user_inst_id: state => {
      return state.user_inst_id
    },
    all_filters: state => {
        return state.filter_by
    },
    filter_by_report_id: state => {
      return state.filter_by.report_id
    },
    filter_by_fromYM: state => {
        return state.filter_by.fromYM
    },
    filter_by_toYM: state => {
        return state.filter_by.toYM
    },
    filter_by_accessmethod: state => {
      return state.filter_by.accessmethod_id
    },
    filter_by_accesstype: state => {
      return state.filter_by.accesstype_id
    },
    filter_by_datatype: state => {
      return state.filter_by.datatype_id
    },
    filter_by_institutiongroup_id: state => {
      return state.filter_by.institutiongroup_id
    },
    filter_by_institution: state => {
      return state.filter_by.inst_id
    },
    filter_by_platform: state => {
      return state.filter_by.platform_id
    },
    filter_by_provider: state => {
      return state.filter_by.provider_id
    },
    filter_by_sectiontype: state => {
      return state.filter_by.sectiontype_id
    },
    filter_by_yop: state => {
        return state.filter_by.yop
    },
    all_options: state => {
        return state.options
    },
    accessmethod_options: state => {
      return state.options.accessmethods
    },
    accesstype_options: state => {
      return state.options.accesstypes
    },
    datatype_options: state => {
      return state.options.datatypes
    },
    institution_options: state => {
      return state.options.institutions
    },
    institutiongroup_options: state => {
      return state.options.institutiongroups
    },
    platform_options: state => {
      return state.options.platforms
    },
    provider_options: state => {
      return state.options.providers
    },
    sectiontype_options: state => {
      return state.options.sectiontypes
    },
    report_data: state => {
      return state.report_data
    },
  }
});
