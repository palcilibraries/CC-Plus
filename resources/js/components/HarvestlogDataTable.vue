<template>
  <div>
    <h3>Harvest Logs</h3>
    <div class="d-flex pa-0 align-center">
      <div v-if="datesFromTo!='|'" class="x-box">
        <img src="/images/red-x-16.png" width="100%" alt="clear date range" @click="clearFilter('date_range')"/>&nbsp;
      </div>
      <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
      ></date-range>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <v-btn class='btn' x-small type="button" @click="clearAllFilters()">Clear Filters</v-btn>
      </v-col>
    </div>
    <v-row no-gutters>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['updated']!=null && mutable_filters['updated']!=''" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('updated')"/>&nbsp;
        </div>
        <v-select :items="mutable_updated" v-model="mutable_filters['updated']" @change="updateFilters()"
                  label="Updated"
        ></v-select>
      </v-col>
      <v-col v-if="institutions.length>1 && (inst_filter==null || inst_filter=='I')"
             class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-select :items="institutions" v-model="mutable_filters['inst']" @change="updateFilters()" multiple
                  label="Institution(s)"  item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if="groups.length>1 && (inst_filter==null || inst_filter=='G') && (is_admin || is_viewer)"
             class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['group'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('group')"/>&nbsp;
        </div>
        <v-select :items="groups" v-model="mutable_filters['group']" @change="updateFilters()" multiple
                  label="Institution Group(s)"  item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-select :items="providers" v-model="mutable_filters['prov']" @change="updateFilters()" multiple
                  label="Provider(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['rept'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        </div>
        <v-select :items="reports" v-model="mutable_filters['rept']" @change="updateFilters()" multiple
                  label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['stat'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('stat')"/>&nbsp;
        </div>
        <v-select :items="statuses" v-model="mutable_filters['stat']" @change="updateFilters()" multiple
                  label="Status(es)" item-text="name" item-value="name"
        ></v-select>
      </v-col>
    </v-row>
    <div v-if='is_admin || is_manager'>
      <v-row class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </v-row>
      <v-row class="d-flex pa-1 align-center" no-gutters>
        <v-col class="d-flex px-2" cols="4" sm="2">
          <v-select :items='bulk_actions' v-model='bulkAction' @change="processBulk()"
                    item-text="action" item-value="status" label="Bulk Actions"
                    :disabled='selectedRows.length==0'></v-select>
        </v-col>
        <v-col v-if="selectedRows.length>0" class="d-flex px-4 align-center" cols="8" sm="4">
          <span class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
        </v-col>
      </v-row>
      <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_harvests" :loading="loading" show-select
                    item-key="id" :options="mutable_options" @update:options="updateOptions" :key="dtKey">
        <template v-slot:item.action="{ item }">
          <v-btn class='btn' x-small type="button" :href="'/harvestlogs/'+item.id+'/edit'">Details</v-btn>
        </template>
      </v-data-table>
    </div>
    <div v-else>
      <v-data-table :headers="headers" :items="mutable_harvests" :loading="loading" item-key="id"
                     :options="mutable_options" @update:options="updateOptions">
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
            groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} },
           },
    data () {
      return {
        headers: [
          { text: 'Last Update', value: 'updated_at' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: 'action' },
        ],
        mutable_harvests: this.harvests,
        mutable_filters: this.filters,
        inst_filter: null,
        mutable_options: {},
        mutable_updated: [],
        statuses: ['Success', 'Fail', 'New', 'Queued', 'Active', 'Pending', 'Stopped', 'ReQueued'],
        status_changeable: ['Stopped', 'Fail', 'New', 'Queued', 'ReQueued'],
        bulk_actions: [ { action:'Stop',    status:'Stopped'},
                        { action:'Restart', status:'Queued'},
                        { action:'Delete',  status:'Delete'}
                      ],
        harv: {},
        selectedRows: [],
        minYM: '',
        maxYM: '',
        dtKey: 1,
        rangeKey: 1,
        bulkAction: '',
        success: '',
        failure: '',
        loading: true,
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to update state and reload records
          // (just not the FIRST change that happens on page load)
          if (this.rangeKey > 1 && this.all_filters.toYM != '' && this.all_filters.fromYM != '' &&
              this.all_filters.toYM != null && this.all_filters.fromYM != null) {
              this.mutable_filters['toYM'] = this.filter_by_toYM;
              this.mutable_filters['fromYM'] = this.filter_by_fromYM;
              this.$store.dispatch('updateAllFilters',this.mutable_filters);
              this.updateLogRecords();
          }
          this.rangeKey += 1;           // force re-render of the date-range component
        }
      },
    },
    methods: {
        // Changing filters means clearing SelectedRows - otherwise Bulk Actions could affect
        // one of many rows no longer displayed.
        updateFilters() {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateLogRecords();
            this.selectedRows = [];
            if (this.mutable_filters['inst'].length>0 || this.mutable_filters['group'].length>0) {
                this.inst_filter = (this.mutable_filters['inst'].length>0) ? "I" : "G";
            }
        },
        clearAllFilters() {
            Object.keys(this.mutable_filters).forEach( (key) =>  {
              if (key == 'fromYM' || key == 'toYM' || key == 'updated') {
                  this.mutable_filters[key] = '';
              } else {
                  this.mutable_filters[key] = [];
              }
            });
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateLogRecords();
            this.inst_filter = null;
            this.rangeKey += 1;           // force re-render of the date-range component
        },
        clearFilter(filter) {
            if (filter == 'date_range') {
                this.mutable_filters['toYM'] = '';
                this.mutable_filters['fromYM'] = '';
                this.rangeKey += 1;           // force re-render of the date-range component
            } else if (filter == 'updated') {
                this.mutable_filters['updated'] = '';
            } else {
                this.mutable_filters[filter] = [];
                if (filter=='inst' || filter=='group') this.inst_filter = null;
            }
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateLogRecords();
            this.selectedRows = [];
        },
        updateLogRecords() {
            self.success = "";
            self.failure = "";
            this.loading = true;
            if (this.filter_by_toYM != null) this.mutable_filters['toYM'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.mutable_filters['fromYM'] = this.filter_by_fromYM;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/harvestlogs?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_harvests = response.data.harvests;
                     this.mutable_updated = response.data.updated;
                     this.numRows = this.mutable_harvests.length;
                 })
                 .catch(err => console.log(err));
             this.loading = false;
        },
        updateOptions(options) {
            if (Object.keys(this.mutable_options).length === 0) return;
            Object.keys(this.mutable_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_options[key]) {
                    this.mutable_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_options);
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
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer', 'filter_by_fromYM', 'filter_by_toYM', 'all_filters',
                     'datatable_options']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
    },
    beforeCreate() {
        // Load existing store data
		this.$store.commit('initialiseStore');
	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','harvestlogs');
	},
    mounted() {
      // Update any null/empty filters w/ store-values
      Object.keys(this.all_filters).forEach( (key) =>  {
        if (key == 'fromYM' || key == 'toYM' || key == 'updated') {
            if (this.mutable_filters[key] == null || this.mutable_filters[key] == "")
                this.mutable_filters[key] = this.all_filters[key];
        } else {
            if (typeof(this.mutable_filters[key]) != 'undefined') {
                if (this.mutable_filters[key].length == 0)
                    this.mutable_filters[key] = this.all_filters[key];
            }
        }
      });

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);

      // Setup date-bounds for the date-selector
      if (typeof(this.bounds[0]) != 'undefined') {
        this.minYM = this.bounds[0].YM_min;
        this.maxYM = this.bounds[0].YM_max;
      }

      // Remove institution column in output if not admin or viewer
      if (!this.is_admin && !this.is_viewer) {
         this.headers.splice(this.headers.findIndex(h=>h.value == "inst_name"),1);
      }

      // Update store and apply filters now that they're set
      this.updateLogRecords();
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
