import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false,
      viewer: false,
      user_inst_id: 0,
      filterby: {
          accessmethod_id: 0,
          accesstype_id: 0,
          datatype_id: 0,
          institutiongroup_id: 0,
          institution_id: 0,
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
    SET_ACCESSMETHOD_FILTER(state, method_id) {
        state.filterby.accessmethod_id = method_id;
    },
    SET_ACCESSTYPE_FILTER(state, type_id) {
        state.filterby.accesstype_id = type_id;
    },
    SET_DATATYPE_FILTER(state, type_id) {
        state.filterby.datatype_id = type_id;
    },
    SET_INSTGROUP_FILTER(state, group_id) {
        state.filterby.institutiongroup_id = group_id;
    },
    SET_INSTITUTION_FILTER(state, inst_id) {
        state.filterby.institution_id = inst_id;
    },
    SET_PLATFORM_FILTER(state, plat_id) {
        state.filterby.platform_id = plat_id;
    },
    SET_PROVIDER_FILTER(state, prov_id) {
        state.filterby.provider_id = prov_id;
    },
    SET_PUBLISHER_FILTER(state, pub_id) {
        state.filterby.publisher_id = pub_id;
    },
    SET_SECTIONTYPE_FILTER(state, type_id) {
        state.filterby.sectiontype_id = type_id;
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
    updateUserInst({ commit }, inst_id) {
      commit('SET_USERINST', inst_id);
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
    filterby_accessmethod_id: state => {
      return state.filterby.accessmethod_id
    },
    filterby_accesstype_id: state => {
      return state.filterby.accesstype_id
    },
    filterby_datatype_id: state => {
      return state.filterby.datatype_id
    },
    filterby_institutiongroup_id: state => {
      return state.filterby.institutiongroup_id
    },
    filterby_institution_id: state => {
      return state.filterby.institution_id
    },
    filterby_platform_id: state => {
      return state.filterby.platform_id
    },
    filterby_provider_id: state => {
      return state.filterby.provider_id
    },
    filterby_publisher_id: state => {
      return state.filterby.publisher_id
    },
    filterby_sectiontype_id: state => {
      return state.filterby.sectiontype_id
    },
  },
});
