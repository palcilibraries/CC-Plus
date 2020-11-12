<template>
  <div>
    <h3>Harvest Logs</h3>
    <div class="d-flex pa-0">
      <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
      ></date-range>
    </div>
    <v-row no-gutters>
      <v-col v-if='institutions.length>1' class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if='mutable_filters.inst.length>0' class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-select :items='institutions' v-model='mutable_filters.inst' @change="updateLogRecords()" multiple
                  label="Institution(s)"  item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if='mutable_filters.prov.length>0' class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-select :items='providers' v-model='mutable_filters.prov' @change="updateLogRecords()" multiple
                  label="Provider(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if='mutable_filters.rept.length>0' class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        </div>
        <v-select :items='reports' v-model='mutable_filters.rept' @change="updateLogRecords()" multiple
                  label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if='mutable_filters.stat.length>0' class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('stat')"/>&nbsp;
        </div>
        <v-select :items='statuses' v-model='mutable_filters.stat' @change="updateLogRecords()" multiple
                  label="Status(es)" item-text="name" item-value="name"
        ></v-select>
      </v-col>
    </v-row>
    <div v-if='is_admin || is_manager'>
      <v-row v-if="success!='' || failure!=''">
        <span class="form-good" role="alert" v-text="success"></span>
        <span class="form-fail" role="alert" v-text="failure"></span>
      </v-row>
      <v-row class="d-flex pa-1"no-gutters>
        <v-col class="d-flex px-2 align-center" cols="4" sm="2">
          <v-select :items='bulk_actions' v-model='bulkAction' @change="processBulk()" label="Bulk Actions"
                    :disabled='selectedRows.length==0'></v-select>
        </v-col>
      </v-row>
      <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_harvests" item-key="id" show-select>
        <template v-slot:item.action="{ item }">
          <v-btn class='btn' x-small type="button" :href="'/harvestlogs/'+item.id+'/edit'">Details</v-btn>
        </template>
      </v-data-table>
    </div>
    <div v-else>
      <v-data-table :headers="headers" :items="mutable_harvests" item-key="id">
        <template v-slot:item.action="{ item }">
          <v-btn class='btn' x-small type="button" :href="'/harvestlogs/'+item.id+'/edit'">Details</v-btn>
        </template>
      </v-data-table>
    </div>
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
          { text: 'Last Update', value: 'updated' },
          { text: 'Institution', value: 'institution' },
          { text: 'Provider', value: 'provider' },
          { text: 'Report', value: 'report' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: 'action' },
        ],
        mutable_harvests: this.harvests,
        mutable_filters: this.filters,
        statuses: ['Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', 'ReQueued'],
        status_changeable: ['Stopped', 'Fail', 'New', 'Queued', 'ReQueued'],
        bulk_actions: ['Stop', 'Restart', 'Delete'],
        harv: {},
        selectedRows: [],
        minYM: '',
        maxYM: '',
        rangeKey: 1,
        bulkAction: '',
        success: '',
        failure: '',
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
            self.success = "";
            self.failure = "";
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
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "Bulk processing will proceed through each requested harvest sequentially. Any selected";
            msg +=  " harvest(s) with a current status of 'Success', 'Active', or 'Pending' will not be changed.";
            msg += "<br><br>";
            if (this.bulkAction == 'Restart') {
                msg += "Updating the status for the selected harvests will reset the attempts counters to zero and";
                msg += " add immediately add the harvests to the processing queue.";
            } else if (this.bulkAction == 'Stop') {
                msg += "Changing the status for the selected harvests will leave the attempts counter intact, and";
                msg += " will prevent future attempts for these harvests. Any currently queued attempts will be";
                msg += " cancelled.";
            } else if (this.bulkAction == 'Delete') {
                msg += "Deleting the selected harvest records is not reversible! No harvested data will be removed or";
                msg += " changed. <br><br><strong>NOTE:</strong> all failure/warning records connected to this harvest";
                msg += " will also be deleted.";
            }
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              // text: msg,
              html: msg,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, Proceed!'
            }).then((result) => {
              if (result.value) {
                self.success = "Working...";
                if (self.bulkAction == 'Delete') {
                    for (let idx=0; idx<self.selectedRows.length; idx++) {
                      var harvest=self.selectedRows[idx];
                      if (self.status_changeable.includes(harvest.status)) {
                        axios.delete('/harvestlogs/'+harvest.id)
                        .then( (response) => {
                          if (response.data.result) {
                            self.mutable_harvests.splice(self.mutable_harvests.findIndex(h=>h.id == harvest.id),1);
                            self.selectedRows.splice(idx,1);
                          } else {
                            self.failure = response.data.msg;
                            return false;
                          }
                        })
                        .catch({});
                      }
                    }
                    if (self.failure == '') self.success = "Selected harvests successfully deleted.";
                } else {
                    self.selectedRows.every(harvest => {
                      if (self.status_changeable.includes(harvest.status)) {
                        axios.post('/update-harvest-status', {
                                   id: harvest.id,
                                   status: self.bulkAction
                        })
                        .then( function(response) {
                          if (response.data.result) {
                            let harvIdx = self.mutable_harvests.findIndex(h=>h.id===harvest.id);
                            Object.assign(self.mutable_harvests[harvIdx] , response.data.harvest);
                          } else {
                            self.failure = response.data.msg;
                            return false;
                          }
                        })
                        .catch(error => {});
                      }
                      return true;
                    });
                    // }
                    if (self.failure == '') self.success = "Selected harvests successfully updated.";
                }
              }
              self.bulkAction = '';
          })
          .catch({});
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer', 'filter_by_fromYM', 'filter_by_toYM']),
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

      // Remove institution column in output if not admin or viewer
      if (!this.is_admin && !this.is_viewer) {
         this.headers.splice(this.headers.findIndex(h=>h.value == "institution"),1);
      }

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style>
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
