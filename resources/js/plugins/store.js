import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false
  },
  mutations: {
    SET_ACCESS(state, access) {
      if (access=='Admin') {
          state.admin=true;
          state.manager=true;
      } else if (access=='Manager'){
          state.admin=false;
          state.manager=true;
      } else {
          state.admin=false;
          state.manager=false;
      }
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
  },
  getters: {
    is_admin: state => {
      return state.admin
    },
    is_manager: state => {
      return state.manager
    },
  },
});
