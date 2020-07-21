<template>
  <div>
    <div v-if="filterable">
      <h3 v-if="header!=''">{{ header }}</h3>
      <date-range :minym="minYM" :maxym="maxYM"
                  :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM"
      ></date-range>
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
      </v-row>
    </div>
    <v-data-table :headers="headers" :items="failed_harvests" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.harvest.sushi_setting.institution.name }}</td>
          <td>{{ item.harvest.sushi_setting.provider.name }}</td>
          <td>{{ item.harvest.report.name }}</td>
          <td>{{ item.harvest.yearmon }}</td>
          <td>{{ item.process_step }}</td>
          <td>{{ item.ccplus_error.severity.name }}</td>
          <td>{{ item.created_at.substr(0,10) }}</td>
          <td>
            <v-btn v-if="item.detail.length>0" color="primary" x-small @click="detailModal(item.detail)">detail</v-btn>
          </td>
        </tr>
      </template>
    </v-data-table>
    <v-dialog v-model="show_details" max-width="400px">
      <v-card>
        <v-card-title>
          <span>Details</span>
          <v-spacer></v-spacer>
          <v-card-text>{{ item_detail }}</v-card-text>
        </v-card-title>
        <v-card-actions>
          <v-btn color="primary" text @click="show_details=false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
            failed_harvests: { type:Array, default: () => [] },
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
          { text: 'Institution', value: 'inst_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Process Step', value: 'process_step' },
          { text: 'Severity', value: 'severity' },
          { text: 'Run Date', value: 'created_at' },
          { }
        ],
        inst_filter: 0,
        prov_filter: 0,
        rept_filter: 0,
        minYM: '',
        maxYM: '',
        show_details: false,
        item_detail: '',
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
            if (this.filter_by_toYM != null) filters['ymto'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) filters['ymfr'] = this.filter_by_fromYM;
            axios.get("/failedharvests?json=1&"+Object.keys(filters).map(key => key+'='+filters[key]).join('&'))
                            .then((response) => {
                this.failed_harvests = response.data.failed;
            })
            .catch(err => console.log(err));
        },
        detailModal(detail) {
            this.show_details = true;
            this.item_detail = detail;
        },
    },
    computed: {
      ...mapGetters(['filter_by_fromYM', 'filter_by_toYM']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
    },
    mounted() {
      console.log('FailedHarvestsData Component mounted.');
      this.minYM = this.bounds[0].YM_min;
      this.maxYM = this.bounds[0].YM_max;
    }
  }
</script>
<style>
</style>
