<template>
  <div>
    <h3>Harvest Logs</h3>
    <div class="d-flex pa-2">
      <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
      ></date-range>
    </div>
    <v-row no-gutters>
      <v-col v-if='institutions.length>1' class="ma-2" cols="2" sm="2">
        <img v-if='mutable_filters.inst.length>0' src="/images/red-x-16.png"
             alt="clear filter" @click="mutable_filters.inst=[]"/>&nbsp;
        <v-select :items='institutions'
                  v-model='mutable_filters.inst'
                  @change="updateLogRecords()"
                  multiple
                  label="Institution(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <img v-if='mutable_filters.prov.length>0' src="/images/red-x-16.png"
             alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        <v-select :items='providers'
                  v-model='mutable_filters.prov'
                  @change="updateLogRecords()"
                  multiple
                  label="Provider(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <img v-if='mutable_filters.rept.length>0' src="/images/red-x-16.png"
             alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        <v-select :items='reports'
                  v-model='mutable_filters.rept'
                  @change="updateLogRecords()"
                  multiple
                  label="Report(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <img v-if='mutable_filters.stat.length>0' src="/images/red-x-16.png"
             alt="clear filter" @click="clearFilter('stat')"/>&nbsp;
        <v-select :items='statuses'
                  v-model='mutable_filters.stat'
                  @change="updateLogRecords()"
                  multiple
                  label="Status(es)"
                  item-text="name"
                  item-value="name"
        ></v-select>
      </v-col>
    </v-row>
    <v-data-table :headers="headers" :items="mutable_harvests" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.updated_at.substr(0,10) }}</td>
          <td>{{ item.institution }}</td>
          <td>{{ item.provider }}</td>
          <td>{{ item.report }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.attempts }}</td>
          <td>{{ item.status }}</td>
          <td>
            <v-btn class='btn' x-small type="button" :href="'/harvestlogs/'+item.id+'/edit'">Details</v-btn>
          </td>
        </tr>
      </template>
    </v-data-table>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import { mapGetters } from 'vuex'
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => ({ymfr:null,ymto:null,inst:[],prov:[],rept:[],stat:[]}) },
           },
    data () {
      return {
        headers: [
          { text: 'Last Update', value: 'updated_at' },
          { text: 'Institution', value: 'institution' },
          { text: 'Provider', value: 'provider' },
          { text: 'Report', value: 'report' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: '' },
        ],
        mutable_harvests: this.harvests,
        mutable_filters: this.filters,
        statuses: ['Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', 'ReQueued'],
        status_changeable: ['Stopped', 'Fail', 'New', 'Queued', 'ReQueued'],
        harv: {},
        minYM: '',
        maxYM: '',
        rangeKey: 1,
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to reload records, just not the FIRST one
          if (this.rangeKey > 1) {
              this.updateLogRecords();
          }
          this.rangeKey += 1;           // force re-render of the date-range component
        }
      },
    },
    methods: {
        updateLogRecords() {
            if (this.filter_by_toYM != null) this.mutable_filters['ymto'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.mutable_filters['ymfr'] = this.filter_by_fromYM;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/harvestlogs?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_harvests = response.data.harvests;
                 })
                 .catch(err => console.log(err));
        },
        clearFilter(filter) {
            this.mutable_filters[filter] = [];
            this.updateLogRecords();
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'filter_by_fromYM', 'filter_by_toYM']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
    },
    mounted() {
      if (typeof(this.bounds[0]) != 'undefined') {
        this.minYM = this.bounds[0].YM_min;
        this.maxYM = this.bounds[0].YM_max;
      }
      if (this.filters['ymfr'] != null) this.$store.dispatch('updateFromYM',this.filters['ymfr']);
      if (this.filters['ymto'] != null) this.$store.dispatch('updateToYM',this.filters['ymto']);

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style>
</style>
