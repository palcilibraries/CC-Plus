<template>
  <div>
    <div class="d-flex pa-0 align-center">
      <v-col class="d-flex px-4 align-center" cols="2" sm="2">
        <v-btn class='btn' small color="primary" @click="updateRecords()">Refresh</v-btn>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <v-btn class='btn' small type="button" @click="clearAllFilters()">Clear Filters</v-btn>
      </v-col>
    </div>
    <v-row no-gutters>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_providers" v-model="mutable_filters['prov']" @change="updateFilters('prov')" multiple
                        label="Provider(s)" item-text="name" item-value="id">
        </v-autocomplete>
      </v-col>
      <v-col v-if="institutions.length>1" class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_institutions" v-model="mutable_filters['inst']" @change="updateFilters('inst')" multiple
                        label="Institution(s)"  item-text="name" item-value="id"
        ></v-autocomplete>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['rept'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        </div>
        <v-select :items="mutable_reports" v-model="mutable_filters['rept']" @change="updateFilters('rept')" multiple
                  label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
    </v-row>
    <v-data-table v-model="selectedRows" :headers="headers" :items="harvest_jobs" :loading="loading" show-select
                  item-key="id" :footer-props="footer_props" :key="dtKey">
    </v-data-table>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
      institutions: { type:Array, default: () => [] },
      providers: { type:Array, default: () => [] },
      reports: { type:Array, default: () => [] },
      filters: { type:Object, default: () => {} },
    },
    data () {
      return {
        headers: [
          { text: 'Queued', value: 'created_at' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Report', value: 'report_name', align: 'center' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Status', value: 'harvest_status' },
          { text: 'Last Error', value: 'last_error', align: 'center' },
        ],
        harvest_jobs: [],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        selectedRows: [],
        mutable_filters: this.filters,
        mutable_reports: [ ...this.reports ],
        mutable_providers: [ ...this.providers ],
        mutable_institutions: [ ...this.institutions ],
        dtKey: 1,
        bulkAction: '',
        success: '',
        failure: '',
        loading: false,
      }
    },
    methods: {
        // Update records from the jobs queue
        updateRecords() {
            this.success = "";
            this.failure = "";
            this.loading = true;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/harvest-jobs?filters="+_filters)
                 .then((response) => {
                     this.harvest_jobs = response.data.jobs;
                     // update provider and inst filter options
                     this.mutable_reports = this.reports.filter( r => response.data.rept_ids.includes(r.id) );
                     this.mutable_providers = this.providers.filter( p => response.data.prov_ids.includes(p.id) );
                     this.mutable_institutions = this.institutions.filter( i => response.data.inst_ids.includes(i.id) );
                     this.loading = false;
                     this.dtKey++;
                 })
                 .catch(err => console.log(err));
        },
        // Changing filters also means clearing SelectedRows
        updateFilters(filt) {
            this.selectedRows = [];
        },
        clearFilter(filter) {
            if ( !Object.keys(this.mutable_filters).includes(filter) ) return;
            this.mutable_filters[filter] = [];
            this.selectedRows = [];
            if (filter == 'rept') {
              this.mutable_reports = [ ...this.reports ];
            } else if (filter == 'prov') {
              this.mutable_providers = [ ...this.providers ];
            } else {  // inst
              this.mutable_institutions = [ ...this.institutions ];
            }
        },
        clearAllFilters() {
            Object.keys(this.mutable_filters).forEach( (key) =>  {
                this.mutable_filters[key] = [];
            });
            this.mutable_reports = [ ...this.reports ];
            this.mutable_providers = [ ...this.providers ];
            this.mutable_institutions = [ ...this.institutions ];
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "";
            if (this.bulkAction != 'Delete') {
                msg = "Bulk processing will proceed through each requested harvest sequentially. Any selected";
                msg +=  " harvest(s) with a current status of 'Success' or 'Pending' will not be changed.";
                msg += "<br><br>";
            }
            if (this.bulkAction == 'Restart') {
                msg += "Updating the status for the selected harvests will reset the attempts counters to zero and";
                msg += " add immediately add the harvests to the processing queue.";
            } else if (this.bulkAction == 'Stop') {
                msg += "Changing the status for the selected harvests will leave the attempts counter intact, and";
                msg += " will prevent future attempts for these harvests. Any currently queued attempts will be";
                msg += " cancelled.";
            } else if (this.bulkAction == 'Delete') {
                msg += "Deleting the selected harvest records is not reversible! Active harvests cannot be deleted, and no";
                msg += " harvested data will be removed or changed. <br><br><strong>NOTE:</strong> all failure/warning records";
                msg += " connected to this harvest will also be deleted.";
            }
            Swal.fire({
              title: 'Are you sure?',
              html: msg,
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, Proceed!'
            }).then((result) => {
              if (result.value) {
                this.success = "Working...";
                if (this.bulkAction == 'Delete') {
                  let settingIDs = this.selectedRows.map( s => s.id );
                  axios.post('/bulk-harvest-delete', { harvests: settingIDs })
                  .then( (response) => {
                    if (response.data.result) {
                      response.data.removed.forEach( _id => {
                        this.mutable_harvests.splice(this.mutable_harvests.findIndex( h => h.id == _id),1);
                      });
                      this.selectedRows = [];
                      this.success = response.data.msg;
                    } else {
                      this.failure = response.data.msg;
                      return false;
                    }
                  })
                  .catch({});
                } else {
                    this.selectedRows.forEach(harvest => {
                      // Allow change to Active
                      if (this.status_changeable.includes(harvest.status) || harvest.status == 'Active') {
                        axios.post('/update-harvest-status', {
                                   id: harvest.id,
                                   status: this.bulkAction
                        })
                        .then( (response) => {
                          if (response.data.result) {
                            var harvIdx = this.mutable_harvests.findIndex(h=>h.id===harvest.id);
                            this.mutable_harvests[harvIdx].status = response.data.status;
                          } else {
                            this.failure = response.data.msg;
                            return false;
                          }
                        })
                        .catch(error => {});
                      }
                    });
                    if (this.failure == '') this.success = "Selected harvests successfully updated.";
                }
              }
              this.dtKey += 1;           // force re-render of the datatable
              this.bulkAction = '';
          })
          .catch({});
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer']),
    },
    mounted() {

      this.updateRecords();

      console.log('HarvestJobs Component mounted.');
    }
  }
</script>
<style scoped>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
