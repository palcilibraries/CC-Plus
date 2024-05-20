<template>
  <div>
    <h1>Usage Report Harvesting</h1>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Manual Harvest -->
      <v-expansion-panel v-if="job_count>0 && is_admin">
        <v-expansion-panel-header>
          <h2>System Job Queue</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <harvestjobs-data-table :institutions="institutions" :providers="providers" :reports="reports" :filters="job_filters"
          ></harvestjobs-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Manual Harvest -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Manual Harvesting</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <p>&nbsp;</p>
          <p>Harvests may be manually added to the CC-Plus harvesting queue once settings are defined to connect
             provider services with one more institutions.
          </p>
          <p>The harvesting queue is automatically scanned on a preset interval established by the CC-Plus administrator.<br />
             The CC-Plus system processes all harvest requests on a first-in first-out basis.
             <h5>Note:</h5>
             <ul>
              <li>Requesting a manual harvest for a previously harvested provider, institition, and month,
                  will <strong>re-initialize the harvest as a new entry</strong> with zero attempts.</li>
              <li>On successful retrieval, manually harvested data will replace (overwrite) all previously
                  harvested report data for a given institution->provider->month.</li>
             </ul>
          </p>
          <manual-harvest :institutions="harvest_insts" :inst_groups="groups" :providers="harvest_provs" :all_reports="reports"
                          :presets="{}" @new-harvests="addHarvests" @updated-harvests="updateHarvests"
          ></manual-harvest>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Harvest Log -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Harvest Log</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <harvestlog-data-table :harvests="mutable_harvests" :institutions="institutions" :groups="groups" :providers="providers"
                                 :reports="reports" :bounds="mutable_bounds" :filters="filters" :codes="codes" :key="harvKey"
          ></harvestlog-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  import Swal from 'sweetalert2';
  import { mapGetters } from 'vuex'
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} },
            codes: { type:Array, default: () => [] },
            job_count: { type:Number, default: 0 },
           },
    data () {
        return {
            failure: '',
            success: '',
            panels: [],
            harvest_provs: [],
            harvest_insts: [],
            mutable_harvests: [...this.harvests],
            mutable_bounds: [...this.bounds],
            job_filters: { 'inst': [], 'prov': [], 'rept':[] },
            harvKey: 1,
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
      updateHarvests (harvests) {
        var updated=0;
        harvests.forEach( (harv) => {
          var idx = this.mutable_harvests.findIndex( h => h.id == harv.id);
          if (idx >= 0) {
            this.mutable_harvests[idx] = harv;
            updated += 1;
          }
        });
        if (updated > 0) this.harvKey += 1;
      },
      addHarvests ({ harvests, bounds }) {
        var added=0;
        harvests.forEach( (harv) => {
          this.mutable_harvests.push(harv);
          added += 1;
        });
        if (added > 0) {
          this.mutable_bounds = [...bounds];
          this.harvKey += 1;
        }
      },
    },
    computed: {
        ...mapGetters(['is_manager', 'is_admin', 'panel_data']),
        current_panels() { return this.panels; }
    },
    beforeCreate() {
        // Load existing store data
        this.$store.commit('initialiseStore');
	  },
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updateDashboard','harvesting');
    },
    mounted() {
        this.harvest_provs = this.providers.filter(p => p.sushi_enabled);
        if (this.harvest_provs.length > 1) {
          this.harvest_provs.unshift({'id': 0, 'name':'All Consortium Providers'});
          this.harvest_provs.unshift({'id':-1, 'name':'All Providers'});
        }
        this.harvest_insts = [ ...this.institutions];

        // Set datatable options with store-values
        Object.assign(this.panels, this.panel_data);

        // Subscribe to store updates
        this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
        console.log('Harvesting Component mounted.');
    }
  }
</script>
<style>
</style>
