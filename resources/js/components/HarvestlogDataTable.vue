<template>
  <div>
    <div v-if="filterable">
      <h3 v-if="header!=''">{{ header }}</h3>
      <date-range :minym="minYM" :maxym="maxYM"
                  :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM"
      ></date-range>
      <!-- :key="rangeKey" -->
      <v-row no-gutters>
        <v-col v-if='institutions.length>1' class="ma-2" cols="2" sm="2">
          <v-select :items='institutions'
                      v-model='inst_filter'
                      @change="updateLogRecords()"
                      label="Institution"
                      item-text="name"
                      item-value="id"
          ></v-select>
        </v-col>
        <v-col class="ma-2" cols="2" sm="2">
          <v-select :items='providers'
                    v-model='prov_filter'
                    @change="updateLogRecords()"
                    label="Provider"
                    item-text="name"
                    item-value="id"
          ></v-select>
        </v-col>
        <v-col class="ma-2" cols="2" sm="2">
          <v-select :items='reports'
                      v-model='rept_filter'
                      @change="updateLogRecords()"
                      label="Report"
                      item-text="name"
                      item-value="id"
          ></v-select>
        </v-col>
        <v-col class="ma-2" cols="2" sm="2">
          <v-select :items='statuses'
                    v-model='stat_filter'
                    @change="updateLogRecords()"
                    label="Status"
                    item-text="name"
                    item-value="name"
          ></v-select>
        </v-col>
      </v-row>
    </div>
    <v-data-table :headers="headers" :items="harvest_logs" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.updated_at.substr(0,10) }}</td>
          <td>{{ item.sushi_setting.institution.name }}</td>
          <td>{{ item.sushi_setting.provider.name }}</td>
          <td>{{ item.report.name }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.attempts }}</td>
          <td>{{ item.status }}</td>
          <td v-if="item.attempts>0"><a :href="'/harvestlogs/'+item.id">details</a></td>
          <td v-else-if="item.rawfile && (is_admin || is_manager)">
              <a :href="'/harvestlogs/'+item.id+'/raw'">Raw data</a>
          </td>
        </tr>
      </template>
    </v-data-table>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filterable: { type:Number, default:0 },
            header: { type:String, default:'' },
           },
    data () {
      return {
        headers: [
          { text: 'Harvested', value: 'created_at' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: '' },
        ],
        harvest_logs: this.harvests,
        statuses: ['ALL', 'Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', 'Retrying'],
        inst_filter: 0,
        prov_filter: 0,
        rept_filter: 0,
        stat_filter: 'ALL',
        minYM: '',
        maxYM: '',
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to reload records
          this.updateLogRecords();
        }
      },
    },
    methods: {
        updateLogRecords() {
            this.minYM = this.bounds[this.rept_filter].YM_min;
            this.maxYM = this.bounds[this.rept_filter].YM_max;
            let filters = {};
            if (this.inst_filter > 0) filters['inst'] = this.inst_filter;
            if (this.prov_filter > 0) filters['prov'] = this.prov_filter;
            if (this.rept_filter > 0) filters['rept'] = this.rept_filter;
            if (this.stat_filter != 'ALL') filters['stat'] = this.stat_filter;
            if (this.filter_by_toYM != null) filters['ymto'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) filters['ymfr'] = this.filter_by_fromYM;
            axios.get("/harvestlogs?json=1&"+Object.keys(filters).map(key => key+'='+filters[key]).join('&'))
                            .then((response) => {
                this.harvest_logs = response.data.harvests;
            })
            .catch(err => console.log(err));
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'filter_by_fromYM', 'filter_by_toYM']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
    },
    mounted() {
      console.log('HarvestLogData Component mounted.');
      this.minYM = this.bounds[0].YM_min;
      this.maxYM = this.bounds[0].YM_max;
    }
  }
</script>
<style>
</style>
