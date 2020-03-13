import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false,
      viewer: false,
      user_inst_id: 0,
      master_report: '',
      filter_by: {
          from_yearmon: '',
          to_yearmon: '',
          accessmethod_id: 0,
          accesstype_id: 0,
          datatype_id: 0,
          institutiongroup_id: 0,
          inst_id: 0,
          platform_id: 0,
          provider_id: 0,
          publisher_id: 0,
          sectiontype_id: 0
      },
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
    SET_MASTERREPORT(state, report) {
        state.master_report = report;
    },
    SET_FROMYM(state, yearmon) {
        state.filter_by.from_yearmon = yearmon;
    },
    SET_TOYM(state, yearmon) {
        state.filter_by.to_yearmon = yearmon;
    },
    SET_ACCESSMETHOD_FILTER(state, method_id) {
        state.filter_by.accessmethod_id = method_id;
    },
    SET_ACCESSTYPE_FILTER(state, type_id) {
        state.filter_by.accesstype_id = type_id;
    },
    SET_DATATYPE_FILTER(state, type_id) {
        state.filter_by.datatype_id = type_id;
    },
    SET_INSTGROUP_FILTER(state, group_id) {
        state.filter_by.institutiongroup_id = group_id;
    },
    SET_INSTITUTION_FILTER(state, inst_id) {
        state.filter_by.inst_id = inst_id;
    },
    SET_PLATFORM_FILTER(state, plat_id) {
        state.filter_by.platform_id = plat_id;
    },
    SET_PROVIDER_FILTER(state, prov_id) {
        state.filter_by.provider_id = prov_id;
    },
    SET_PUBLISHER_FILTER(state, pub_id) {
        state.filter_by.publisher_id = pub_id;
    },
    SET_SECTIONTYPE_FILTER(state, type_id) {
        state.filter_by.sectiontype_id = type_id;
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
    updateUserInst({ commit }, inst_id) {
      commit('SET_USERINST', inst_id);
    },
    updateMasterReport({ commit }, report) {
      commit('SET_MASTERREPORT', report);
    },
    updateFromYM({ commit }, yearmon) {
      commit('SET_FROMYM', yearmon);
    },
    updateToYM({ commit }, yearmon) {
      commit('SET_TOYM', yearmon);
    },
    updateAccessMethodFilter({ commit }, method_id) {
      commit('SET_ACCESSMETHOD_FILTER', method_id);
    },
    updateAccessTypeFilter({ commit }, type_id) {
      commit('SET_ACCESSTYPE_FILTER', type_id);
    },
    updateDataTypeFilter({ commit }, type_id) {
      commit('SET_DATATYPE_FILTER', type_id);
    },
    updateInstGroupFilter({ commit }, group_id) {
      commit('SET_INSTGROUP_FILTER', group_id);
    },
    updateInstitutionFilter({ commit }, inst_id) {
      commit('SET_INSTITUTION_FILTER', inst_id);
    },
    updatePlatformFilter({ commit }, plat_id) {
      commit('SET_PLATFORM_FILTER', plat_id);
    },
    updateProviderFilter({ commit }, prov_id) {
      commit('SET_PROVIDER_FILTER', prov_id);
    },
    updatePublisherFilter({ commit }, pub_id) {
      commit('SET_PUBLISHER_FILTER', pub_id);
    },
    updateSectionTypeFilter({ commit }, type_id) {
      commit('SET_SECTIONTYPE_FILTER', type_id);
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
    master_report: state => {
      return state.master_report
    },
    all_filters: state => {
        return state.filter_by
    },
    filter_by_from_yearmon: state => {
        return state.filter_by.from_yearmon
    },
    filter_by_to_yearmon: state => {
        return state.filter_by.to_yearmon
    },
    filter_by_accessmethod_id: state => {
      return state.filter_by.accessmethod_id
    },
    filter_by_accesstype_id: state => {
      return state.filter_by.accesstype_id
    },
    filter_by_datatype_id: state => {
      return state.filter_by.datatype_id
    },
    filter_by_institutiongroup_id: state => {
      return state.filter_by.institutiongroup_id
    },
    filter_by_institution_id: state => {
      return state.filter_by.inst_id
    },
    filter_by_platform_id: state => {
      return state.filter_by.platform_id
    },
    filter_by_provider_id: state => {
      return state.filter_by.provider_id
    },
    filter_by_publisher_id: state => {
      return state.filter_by.publisher_id
    },
    filter_by_sectiontype_id: state => {
      return state.filter_by.sectiontype_id
    },
  },
});
