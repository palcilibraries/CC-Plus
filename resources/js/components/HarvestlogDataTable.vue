<template>
  <div>
    <h3>Harvest Logs</h3>
    <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM"
                :key="rangeKey"
    ></date-range>
    <v-row no-gutters>
      <v-col v-if='institutions.length>1' class="ma-2" cols="2" sm="2">
        <v-select :items='institutions'
                  v-model='filters.inst'
                  @change="updateLogRecords()"
                  multiple
                  label="Institution(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <v-select :items='providers'
                  v-model='filters.prov'
                  @change="updateLogRecords()"
                  multiple
                  label="Provider(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <v-select :items='reports'
                  v-model='filters.rept'
                  @change="updateLogRecords()"
                  multiple
                  label="Report(s)"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col class="ma-2" cols="2" sm="2">
        <v-select :items='statuses'
                  v-model='filters.stat'
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
          <td>{{ item.sushi_setting.institution.name }}</td>
          <td>{{ item.sushi_setting.provider.name }}</td>
          <td>{{ item.report.name }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.attempts }}</td>
          <td width="10%" style="vertical-align:middle">
            <!-- Some statuses should not be changed by user-->
            <div v-if="(is_manager || is_admin) && !(status_notset.includes(item.status))">
              <v-select :items="status_canset" v-model="item.status" value="item.status" dense outlined
                        @change="updateStatus(item)"
              ></v-select>
            </div>
            <div v-else>{{ item.status }}</div>
          </td>
          <td v-if="item.attempts>0">
            <a :href="'/harvestlogs/'+item.id"><v-btn color="primary" x-small>detail</v-btn></a>
          </td>
          <td v-else-if="item.rawfile && (is_admin || is_manager)">
            <a :href="'/harvestlogs/'+item.id+'/raw'"><v-btn color="primary" x-small>Raw data</v-btn></a>
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
            filters: { type:Object, default: () => {} },
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
        mutable_harvests: this.harvests,
        prior_status: [],
        statuses: ['Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', 'Retrying'],
        status_canset: ['Stopped', 'Fail', 'New', 'Queued', 'Retrying', 'Delete'],
        status_notset: ['Success', 'Active', 'Pending'],
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
            if (this.filter_by_toYM != null) this.filters['ymto'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.filters['ymfr'] = this.filter_by_fromYM;
            let _filters = JSON.stringify(this.filters);
            axios.get("/harvestlogs?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_harvests = response.data.harvests;
                 })
                 .catch(err => console.log(err));
        },
        updateStatus(harvest) {
            let msg = "";
            if (harvest.status == 'Delete') {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "This action is not reversible, and no harvested data will be removed or changed. "+
                        "Note that all failure/warning records connected to this harvest will also be deleted.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, Proceed!'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/harvestlogs/'+harvest.id)
                           .then( (response) => {
                               if (response.data.result) {
                                   self.failure = '';
                                   self.success = response.data.msg;
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                       this.mutable_harvests.splice(this.mutable_harvests.findIndex(a=> a.id == harvest.id),1);
                  }
                })
                .catch({});
            } else {
                if (harvest.status == 'New' || harvest.status == 'Retrying') {
                    msg += "Updating this harvest status will reset the attempts counter to zero and cause the";
                    msg += " system to include this harvest in the overnight queue-processing cycle.";
                } else if (harvest.status == 'Queued') {
                    msg += "Setting this harvest to 'Queued' will reset the attempts counter to zero and immediately";
                    msg += " append this harvest to the harvesting queue. Any usage data that may have been stored";
                    msg += " for this harvest will be replaced.";
                } else {
                    msg += "Changing this harvest's status will leave the attempts counter intact, and will prevent";
                    msg += " the system from running this harvest. Any future or queued attempts will be cancelled.";
                }
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: msg,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, Proceed!'
                }).then((result) => {
                  if (result.value) {
                    axios.post('/update-harvest-status', {
                        id: harvest.id,
                        status: harvest.status
                    })
                    .catch(error => {});
                    // update prior_status to the new value
                    this.prior_status[this.prior_status.findIndex(h=> h.id == harvest.id)].status = harvest.status;
                    // reset attempts in mutable_harvest if needed
                    if (harvest.status == 'New' || harvest.status == 'Retrying' || harvest.status == 'Queued') {
                        this.mutable_harvests[this.mutable_harvests.findIndex(h=> h.id == harvest.id)].attempts = 0;
                    }
                  } else {
                    // reset mutable_harvest status back to its prior value
                    this.mutable_harvests[this.mutable_harvests.findIndex(h=> h.id == harvest.id)].status =
                         this.prior_status[this.prior_status.findIndex(h=> h.id == harvest.id)].status;
                  }
              })
              .catch({});
            }
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

      // Save the original status values in an array
      this.harvests.forEach(harv => { this.prior_status.push({id: harv.id, status: harv.status}) });

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style>
</style>
