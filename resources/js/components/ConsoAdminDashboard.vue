<template>
  <div>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Users -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Users</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <user-data-table :institutions="mutable_institutions" :allowed_roles="roles" :all_groups="mutable_groups"
                           @new-inst="newInst" :key="userKey"
          ></user-data-table>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Institutions -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Institutions</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <institution-data-table :institutions="mutable_institutions" :filters="inst_filters" :all_groups="mutable_groups"
                                  @new-inst="newInst" @drop-inst="dropInst" @bulk-update="replaceInst"
                                  @groups-updated="groupsUpdated" :key="instKey"
          ></institution-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Institution Types - would go here, if that becomes a thing again-->
      <!--
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Institution Types</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <institution-types :types></institution-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      -->
      <!-- Providers -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Providers</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <provider-data-table :providers="mutable_providers" :institutions="mutable_institutions" :unset_global="mutable_unset"
                               @connect-prov="connectProv" @disconnect-prov="disconnectProv" :key="provKey"
          ></provider-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Settings -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Sushi Connections</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <sushisettings-data-table :providers="mutable_providers" :institutions="mutable_institutions" :filters="sushi_filters"
                                    :inst_groups="mutable_groups" :refresh_key="refreshSushi" :key="sushiKey"
          ></sushisettings-data-table>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
      roles: { type:Array, default: () => [] },
      institutions: { type:Array, default: () => [] },
      groups: { type:Array, default: () => [] },
      providers: { type:Array, default: () => [] },
      master_reports: { type:Array, default: () => [] },
      unset_global: { type:Array, default: () => [] },
    },
    data () {
      return {
        panels: [0],     // default to first panel is open
        userKey: 1,
        instKey: 1,
        provKey: 1,
        sushiKey: 1,
        refreshSushi: 1,
        mutable_institutions: [...this.institutions],
        mutable_providers: [...this.providers],
        mutable_unset: [...this.unset_global],
        mutable_groups: [...this.groups],
        inst_filters: {stat: "", groups: [] },
        sushi_filters: {inst: [], group: 0, prov: [], harv_stat: []},
      }
    },
    watch: {
      current_panels: {
         handler () {
             this.$store.dispatch('updatePanels',this.panels);
         },
         deep: true
       }
    },
    methods: {
      newInst (inst) {
        if (inst.length>0) {
          for (const ii of inst) {
              this.mutable_institutions.push(ii);
          }
          this.mutable_institutions.sort((a,b) => {
            if ( a.name < b.name ) return -1;
            if ( a.name > b.name ) return 1;
            return 0;
          });
          this.instKey += 1;
        }
      },
      dropInst (instId) {
        this.mutable_institutions.splice(this.mutable_institutions.findIndex(ii => ii.id==instId),1);
        this.instKey += 1;
        this.provKey += 1; // inform the provider component of the change
        this.sushiKey += 1;
        this.refreshSushi += 1;
    },
      replaceInst (institutions) {
        this.mutable_institutions = [ ...institutions ];
        this.instKey += 1;
        this.provKey += 1; // inform the provider component of the change
        this.sushiKey += 1;
      },
      groupsUpdated ( {groups, institutions} ) {
        this.mutable_groups = [...groups];
        this.mutable_institutions = [...institutions];
        this.userKey += 1;
        this.instKey += 1;
        this.sushiKey += 1;
      },
      connectProv (prov) {
        this.mutable_providers.push(prov);
        this.mutable_providers.sort((a,b) => {
          if ( a.name < b.name ) return -1;
          if ( a.name > b.name ) return 1;
          return 0;
        });
        this.mutable_unset.splice(this.mutable_unset.findIndex(p => p.id==prov.global_id),1);
        this.provKey += 1;
        this.sushiKey += 1;
      },
      disconnectProv ({ provid, global_prov }) {
        this.mutable_providers.splice(this.mutable_providers.findIndex(p => p.id==provid),1);
        this.mutable_unset.push(global_prov);
        this.mutable_unset.sort((a,b) => {
          if ( a.name < b.name ) return -1;
          if ( a.name > b.name ) return 1;
          return 0;
        });
        this.provKey += 1;
        this.sushiKey += 1;
        this.refreshSushi += 1;
      },
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
      this.$store.dispatch('updatePageName','consoadminhome');
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
