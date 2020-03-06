import Vue from 'vue';
import Vuex from 'vuex';

Vue.use(Vuex);

export const store = new Vuex.Store({
  state: {
      admin: false,
      manager: false,
      user_inst_id: 0
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
    SET_INST(state, inst_id) {
        state.user_inst_id = inst_id;
    },
  },
  actions: {
    updateAccess({ commit }, access) {
      commit('SET_ACCESS', access);
    },
    updateInst({ commit }, inst_id) {
      commit('SET_INST', inst_id);
    },
  },
  getters: {
    is_admin: state => {
      return state.admin
    },
    is_manager: state => {
      return state.manager
    },
    user_inst_id: state => {
      return state.user_inst_id
    },
  },
});
