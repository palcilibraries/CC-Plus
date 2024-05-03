<template>
  <div>
    <h1>Server Administration</h1>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Instances -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Consortial Instances</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <global-instances :consortia="consortia"></global-instances>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Providers -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Platform Definitions</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <global-provider-data-table :providers="providers" :master_reports="master_reports"
                                      :all_connectors="all_connectors" :filters="provider_filters"
          ></global-provider-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Settings -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Server Settings</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <global-settings :settings="settings"></global-settings>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
      consortia: { type:Array, default: () => [] },
      providers: { type:Array, default: () => [] },
      provider_filters: { type:Object, default: () => {} },
      master_reports: { type:Array, default: () => [] },
      all_connectors: { type:Array, default: () => [] },
      settings: { type:Object, default: () => {} },
    },
    data () {
      return {
        panels: [],     // default to all panels closed
      }
    },
    watch: {
      current_panels: {
         handler () {
             this.$store.dispatch('updatePanels',this.panels);
         },
       }
    },
    methods: {
    },
    computed: {
      ...mapGetters(['panel_data']),
      current_panels() { return this.panels; }
    },
    beforeCreate() {
      // Load existing store data
      this.$store.commit('initialiseStore');
  	},
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updateDashboard','globaladminhome');
  	},
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.panels, this.panel_data);

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('GlobalAdmin Dashboard mounted.');
    }
  }
</script>
<style>
</style>
