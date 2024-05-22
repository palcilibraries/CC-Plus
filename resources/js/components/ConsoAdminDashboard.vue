<template>
  <div>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Institutions -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Institutions</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <institution-data-table :key="instKey" :institutions="mutable_institutions" :filters="inst_filters"
                                  :all_groups="mutable_groups" @new-inst="newInst" @drop-inst="dropInst" @bulk-update="bulkInst"
                                  @change-inst="updInst" @refresh-groups="refreshGroups"
          ></institution-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Institution Groups</h2>
        </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <institution-groups :key="groupKey" :groups="mutable_groups" @update-groups="updateGroups"></institution-groups>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Users -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Users</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <user-data-table :institutions="mutable_institutions" :allowed_roles="roles" :all_groups="mutable_groups"
                           @new-inst="newInst" :key="userKey"
          ></user-data-table>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>

      <!-- Institution Types - would go here, if that becomes a thing again-->
      <!--
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Institution Types</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <institution-types :types></institution-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      -->
      <!-- Providers -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Providers</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <provider-data-table :key="provKey" :providers="mutable_providers" :institutions="mutable_institutions"
                               :master_reports="master_reports" @connect-prov="connectProv" @disconnect-prov="disconnectProv"
                               @change-prov="updateProv" @bulk-update="bulkProv"
          ></provider-data-table>
        </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Settings -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>SUSHI Credentials</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <sushisettings-data-table :key="sushiKey" :providers="mutable_providers" :institutions="mutable_institutions"
                                    :inst_groups="mutable_groups" :unset="mutable_unset"
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
        panels: [],     // default to all panels closed
        userKey: 1,
        instKey: 1,
        provKey: 1,
        sushiKey: 1,
        groupKey: 1,
        mutable_institutions: [...this.institutions],
        mutable_providers: [...this.providers],
        mutable_unset: [...this.unset_global],
        mutable_groups: [...this.groups],
        inst_filters: {stat: "", groups: [] },
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
          this.groupKey += 1;
        }
      },
      dropInst (instId) {
        this.mutable_institutions.splice(this.mutable_institutions.findIndex(ii => ii.id==instId),1);
        this.instKey += 1;
        this.provKey += 1; // inform the provider component of the change
        this.sushiKey += 1;
        this.groupKey += 1;
      },
      bulkInst (institutions) {
        this.mutable_institutions = [ ...institutions ];
        this.provKey += 1; // inform the provider component of the change
        this.sushiKey += 1;
        this.groupKey += 1;
      },
      bulkProv (providers) {
        this.mutable_providers = [ ...providers ];
        this.sushiKey += 1;
        this.groupKey += 1;
      },
      // Replace mutable groups and mutable_institutions with emitted arrays
      refreshGroups ( {groups, insts} ) {
        this.mutable_groups = [...groups];
        this.mutable_institutions = [...insts];
        this.sushiKey += 1;
        this.userKey += 1;
        this.groupKey += 1;
      },
      // Replace mutable groups, and update mutable institutions 'groups' string values
      updateGroups ( {groups, membership} ) {
        this.mutable_groups = [...groups];
        if (typeof(membership) == 'undefined') return;
        if (membership.length == 0) return;
        for (let idx=0; idx<this.mutable_institutions.length; idx++) {
            let btInst = membership.find(ii => ii.id == this.mutable_institutions[idx].id);
            if (btInst) {
              this.mutable_institutions[idx].groups = btInst.groups;
            }
        }
        this.instKey += 1;
      },
      updInst (instId) {
        this.sushiKey += 1;
      },
      updateProv (prov) {
        var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
        this.mutable_providers.splice(idx,1,prov);
        this.sushiKey += 1;
      },
      connectProv (prov) {
        var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
        if (idx >= 0) this.mutable_providers.splice(idx,1,prov);
        var uidx = this.mutable_unset.findIndex(p => p.id == prov.id);
        if (uidx >= 0) this.mutable_unset.splice(uidx,1);
        this.sushiKey += 1;
      },
      disconnectProv (prov) {
        var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
        this.mutable_providers.splice(idx,1,prov);
        let global_data = prov.global_prov;
        this.mutable_unset.push(global_data);
        this.mutable_unset.sort((a,b) => {
          if ( a.name < b.name ) return -1;
          if ( a.name > b.name ) return 1;
          return 0;
        });
        this.sushiKey += 1;
      },
    },
    beforeCreate() {
      // Initialize local datastore if it is not there
      if (!localStorage.getItem('store')) {
          this.$store.commit('initialiseStore');
      }
	  },
    mounted() {

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('GlobalAdmin Dashboard mounted.');
    }
  }
</script>
<style>
</style>
