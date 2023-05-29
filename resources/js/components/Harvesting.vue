<template>
  <div>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Manual Harvest -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h3>Manual Harvesting</h3>
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
                          :presets="{}"
          ></manual-harvest>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Harvest Log -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h3>Harvest Log</h3>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <harvestlog-data-table :harvests="harvests" :institutions="institutions" :groups="groups" :providers="providers"
                                 :reports="reports" :bounds="bounds" :filters="filters"
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
           },
    data () {
        return {
            failure: '',
            success: '',
            panels: [0],     // default to first panel is open
            harvest_provs: [],
            harvest_insts: [],
        }
    },
    methods: {
    },
    computed: {
        ...mapGetters(['is_manager', 'is_admin']),
    },
    mounted() {
        this.harvest_provs = [ ...this.providers];
        this.harvest_provs.unshift({'id':0, 'name':'All Providers'});
        this.harvest_insts = [ ...this.institutions];
        this.harvest_insts.unshift({'id':0, 'name':'Entire Consortium'});
        console.log('Harvesting Component mounted.');
    }
  }
</script>
<style>
</style>
