<template>
  <div>
    <div class="d-flex pa-0 align-center">
      <div v-if="datesFromTo!='|'" class="x-box">
        <img src="/images/red-x-16.png" width="100%" alt="clear date range" @click="clearFilter('date_range')"/>&nbsp;
      </div>
      <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
      ></date-range>
      <v-col class="d-flex px-4 align-center" cols="2" sm="2">
        <v-btn class='btn' small color="primary" @click="updateLogRecords()">{{ update_button }}</v-btn>
      </v-col>
      <v-col class="d-flex px-4 align-center" cols="2" sm="2">
        <v-btn class='btn' small type="button" @click="clearAllFilters()">Clear Filters</v-btn>
      </v-col>
      <v-col v-if="truncatedResult" class="d-flex px-2 align-center" cols="4">
        <span class="fail" role="alert">Display Truncated To 500 Records</span>
      </v-col>
    </div>
    <v-row no-gutters>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['updated']!=null && mutable_filters['updated']!=''" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('updated')"/>&nbsp;
        </div>
        <v-select :items="mutable_updated" v-model="mutable_filters['updated']" @change="updateFilters('updated')"
                  label="Updated"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_options['providers']" v-model="mutable_filters['prov']" @change="updateFilters('prov')"
                        multiple label="Provider(s)" item-text="name" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="updateAllProvs">
               <span v-if="allConsoProvs">Disable All</span>
               <span v-else>All Consortium Providers</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col v-if="institutions.length>1 && (inst_filter==null || inst_filter=='I')"
             class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_options['institutions']" v-model="mutable_filters['inst']" @change="updateFilters('inst')"
                        multiple label="Institution(s)"  item-text="name" item-value="id">
          <template v-if="is_admin || is_viewer" v-slot:prepend-item>
            <v-list-item @click="updateAllInsts">
               <span v-if="allConsoInsts">Disable All</span>
               <span v-else>All Institutions</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
        </v-autocomplete>
      </v-col>
      <v-col v-if="groups.length>1 && (inst_filter==null || inst_filter=='G') && (is_admin || is_viewer)"
             class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['group'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('group')"/>&nbsp;
        </div>
        <v-autocomplete :items="groups" v-model="mutable_filters['group']" @change="updateFilters('group')" multiple
                        label="Institution Group(s)"  item-text="name" item-value="id"
        ></v-autocomplete>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['rept'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['reports']" v-model="mutable_filters['rept']" @change="updateFilters('rept')" multiple
                  label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['harv_stat'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('harv_stat')"/>&nbsp;
        </div>
        <v-select :items="mutable_options['statuses']" v-model="mutable_filters['harv_stat']" @change="updateFilters('harv_stat')"
                  multiple label="Status(es)" item-text="name" item-value="name"
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
        <v-col class="d-flex px-4 align-center" cols="4">
          <span v-if="selectedRows.length>0" class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
          <span v-else>&nbsp;</span>
        </v-col>
        <v-col class="d-flex px-2 align-center" cols="2">
          <div v-if="mutable_filters['source']!=null && mutable_filters['source']!=''" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('source')"/>&nbsp;
          </div>
          <v-select :items="source" v-model="mutable_filters['source']" @change="updateFilters('source')"
                    label="Harvested For"
          ></v-select>
        </v-col>
        <v-col v-if="mutable_options['codes'].length>0" class="d-flex px-2 align-center" cols="2">
          <div v-if="mutable_filters['codes'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('codes')"/>&nbsp;
          </div>
          <v-select :items="mutable_options['codes']" v-model="mutable_filters['codes']" @change="updateFilters('codes')" multiple
                    label="Error Code">
            <template v-slot:prepend-item>
              <v-list-item @click="filterAllCodes">
                 <span v-if="allCodes">Disable All</span>
                 <span v-else>Enable All</span>
              </v-list-item>
              <v-divider class="mt-1"></v-divider>
            </template>
          </v-select>
        </v-col>
      </v-row>
      <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_harvests" :loading="loading" show-select
                    item-key="id" :options="mutable_dt_options" @update:options="updateOptions" :footer-props="footer_props"
                    :expanded="expanded" @click:row="expandRow" show-expand :key="dtKey">
        <template v-slot:item.prov_name="{ item }">
          <span v-if="item.prov_inst_id==1">
            <v-icon title="Consortium Provider">mdi-account-multiple</v-icon>&nbsp;
          </span>
          {{ item.prov_name }}
        </template>
        <template v-slot:item.error_code="{ item }">
          <span v-if="item.error_code==null && item.status=='Success'">
            <v-icon title="Download Raw JSON Data" @click="goURL('/harvests/'+item.id+'/raw')">mdi-download</v-icon>
          </span>
          <span v-else> {{ item.error_code }}</span>
        </template>
        <template v-slot:item.data-table-expand="{ item, isExpanded, expand }">
          <v-icon title="Error Details" @click="expand(true)" v-if="item.error_code>0 && !isExpanded" color="#F29727">
            mdi-alert-outline
          </v-icon>
          <v-icon title="Close" @click="expand(false)" v-if="item.error_code>0 && isExpanded">mdi-close</v-icon>
        </template>
        <template v-slot:expanded-item="{ headers, item }">
          <td v-if="item.failed.length>0" :colspan="headers.length">
            <v-row class="d-flex py-2 justify-center" no-gutters>
              <strong>Failed Harvest Attempts (Harvest ID: {{ item.id }})</strong>
              <span>
                <v-icon title="Download Last JSON Error Message" @click="goURL('/harvests/'+item.id+'/raw')">mdi-download</v-icon>
              </span>
            </v-row>
            <v-row class="d-flex pa-1 align-center" no-gutters>
              <v-col class="d-flex px-2" cols="2"><strong>Attempted</strong></v-col>
              <v-col class="d-flex px-2" cols="8"><strong>Message</strong></v-col>
              <v-col class="d-flex px-2" cols="1"><strong>Help</strong></v-col>
              <v-col class="d-flex px-2" cols="1"><strong>Error</strong></v-col>
            </v-row>
            <v-row class="d-flex py-1 align-center" no-gutters><hr width="100%"></v-row>
            <div v-for="attempt in item.failed" :key="item.id" class="report-field">
              <v-row class="d-flex ma-0" no-gutters>
                <v-col class="d-flex px-2" cols="2">{{ attempt.ts }}</v-col>
                <v-col class="d-flex px-2" cols="8">
                  {{ attempt.message }}
                </v-col>
                <v-col class="d-flex px-2" cols="1">
                  <span v-if="!attempt.help_url || attempt.help_url.trim().length === 0">&nbsp;</span>
                  <span v-else>
                    <v-icon title="Provider Error Help" @click="goURL(attempt.help_url)">mdi-help-box-outline</v-icon>
                  </span>
                </v-col>
                <v-col class="d-flex px-2" cols="1">
                  {{ attempt.code }}
                  <span v-if="attempt.code>=1000 && attempt.code<9000">
                    <v-icon title="COUNTER Error Details" @click="goCounter()">mdi-open-in-new</v-icon>
                  </span>
                </v-col>
              </v-row>
              <v-row v-if="attempt.detail.length>0" class="d-flex ma-0" no-gutters>
                <v-col class="d-flex" cols="2">&nbsp;</v-col>
                <v-col class="d-flex" cols="8">{{ attempt.detail }}</v-col>
                <v-col class="d-flex" cols="2">&nbsp;</v-col>
              </v-row>
            </div>
          </td>
        </template>
      </v-data-table>
    </div>
    <div v-else>
      <v-data-table :headers="headers" :items="mutable_harvests" :loading="loading" item-key="id"
                    :options="mutable_dt_options" @update:options="updateOptions" :footer-props="footer_props">
        <template v-slot:item.prov_name="{ item }">
          <span v-if="item.prov_inst_id==1">
            <v-icon title="Consortium Provider">mdi-account-multiple</v-icon>&nbsp;
          </span>
          {{ item.prov_name }}
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
            errors: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} },
            codes: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Last Update', value: 'updated' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Report', value: 'report_name', align: 'center' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts', align: 'center' },
          { text: 'Status', value: 'status' },
          { text: 'Error Code', value: 'error_code', align: 'center' },
          { text: '', value: 'data-table-expand' },
        ],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        mutable_harvests: this.harvests,
        mutable_filters: this.filters,
        inst_filter: null,
        mutable_dt_options: {},
        mutable_updated: [],
        allConsoProvs: false,
        allConsoInsts: false,
        allCodes: false,
        expanded: [],
        mutable_options: { 'codes': [], 'reports': [], 'statuses': [], 'providers': [], 'institutions': [] },
        source: ['Consortium', 'Institution'],
        truncatedResult: false,
        statuses: ['Active', 'Fail', 'Queued', 'Stopped', 'Success'],
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
        loading: false,
        update_button: "Display Records",
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
          }
          this.rangeKey += 1;           // force re-render of the date-range component
        }
      },
    },
    methods: {
        // Changing filters means clearing SelectedRows - otherwise Bulk Actions could affect
        // one of many rows no longer displayed.
        updateFilters(filt) {
            // if All-insts is enabled, keep other checkboxes clear
            if ( (filt == "inst" || filt == "group") && this.allConsoInsts) {
                // All Institutions checkbox just got cleared?
                if (this.mutable_filters['inst'].length == 0 && filt == "inst") {
                    this.allConsoInsts = false;
                    this.mutable_options['institutions'].splice(this.mutable_options['institutions'].findIndex(ii => ii.id==0),1);
                } else {
                    this.mutable_filters['inst'] = [0];
                    this.mutable_filters['group'] = [];
                    return;
                }
            }
            // if All-provs is enabled, keep other checkboxes clear
            if (filt == "prov" && this.allConsoProvs) {
                // All Providers checkbox just got cleared?
                if (this.mutable_filters['prov'].length == 0) {
                    this.allConsoProvs = false;
                    this.mutable_options['providers'].splice(this.mutable_options['providers'].findIndex(ii => ii.id==0),1);
                } else {
                    this.mutable_filters['prov'] = [0];
                    return;
                }
            }
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
            if (this.mutable_filters['inst'].length>0 || this.allConsoInsts) {
                this.inst_filter = "I";
                this.mutable_filters['group'] = [];
                // Checked the box for all consortium insts - clear other checked insts in the filter
                if (this.allConsoInsts) {
                    this.mutable_filters['inst'] = [];
                }
            } else if (this.mutable_filters['group'].length>0) {
                this.inst_filter = "G";
                this.mutable_filters['inst'] = [];
            }
            if (filt == 'codes') {
              // if all the codes just got turned on, update the allCodes flag
              if (!this.allCodes && this.mutable_filters['codes'].length == this.mutable_options['codes'].length) {
                this.allCodes = true;
              }
            }
        },
        clearAllFilters() {
            Object.keys(this.mutable_filters).forEach( (key) =>  {
              if (key == 'fromYM' || key == 'toYM' || key == 'updated' || key == 'source') {
                  this.mutable_filters[key] = '';
              } else {
                  this.mutable_filters[key] = [];
              }
            });
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            // Reset error code options to inbound property
            Object.keys(this.mutable_options).forEach( (key) => {
              this.mutable_options[key] = [...this[key]];
            });
            this.inst_filter = null;
            this.allConsoProvs = false;
            this.allConsoInsts = false;
            this.allCodes = false;
            this.rangeKey += 1;           // force re-render of the date-range component
        },
        clearFilter(filter) {
            if (filter == 'date_range') {
                this.mutable_filters['toYM'] = '';
                this.mutable_filters['fromYM'] = '';
                this.rangeKey += 1;           // force re-render of the date-range component
            } else if (filter == 'updated' || filter == 'source') {
                this.mutable_filters[filter] = '';
            } else {
                this.mutable_filters[filter] = [];
                if (filter=='inst' || filter=='group') this.inst_filter = null;
                if ( Object.keys(this.mutable_options).includes(filter) ) {
                  this.mutable_options[filter] = [...this[filter]];
                }
            }
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.selectedRows = [];
        },
        // filt holds the filter options to be left alone when the JSON returns options
        updateLogRecords(filt) {
            this.success = "";
            this.failure = "";
            this.loading = true;
            if (this.filter_by_toYM != null) this.mutable_filters['toYM'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.mutable_filters['fromYM'] = this.filter_by_fromYM;
            if (this.allConsoInsts) this.mutable_filters['inst'] = [0];
            if (this.allConsoProvs) this.mutable_filters['prov'] = [0];
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/harvests?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_harvests = response.data.harvests;
                     this.mutable_updated = response.data.updated;
                     this.truncatedResult = response.data.truncated;
                     this.mutable_options['codes'] = response.data.code_opts;
                     this.mutable_options['statuses'] = response.data.stat_opts;
                     this.mutable_options['reports'] = this.reports.filter( r => response.data.rept_opts.includes(r.id));
                     this.mutable_options['providers'] = this.providers.filter( p => response.data.prov_opts.includes(p.id));
                     this.mutable_options['institutions'] = this.institutions.filter( i => response.data.inst_opts.includes(i.id));
                     this.update_button = "Refresh Records";
                     this.loading = false;
                     this.dtKey++;
                 })
                 .catch(err => console.log(err));
        },
        updateOptions(options) {
            if (Object.keys(this.mutable_dt_options).length === 0) return;
            Object.keys(this.mutable_dt_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_dt_options[key]) {
                    this.mutable_dt_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_dt_options);
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
        goEdit (logId) {
            window.location.assign('/harvests/'+logId+'/edit');
        },
        goCounter() {
            window.open("https://cop5.projectcounter.org/en/5.0.3/appendices/f-handling-errors-and-exceptions.html", "_blank");
        },
        goURL(url) {
          window.open(url, "_blank");
        },
        expandRow (item) {
          this.expanded = item === this.expanded[0] ? [] : [item]
        },
        // @change function for filtering/clearing all consortium providers
        filterConsoProv() {
          // Just checked the box for all consortium providers
          if (this.allConsoProvs) {
            this.consortiumProviders.forEach( (cp) => {
              if (!this.mutable_filters['prov'].includes(cp.id)) {
                this.mutable_filters['prov'].push(cp.id);
              }
            });
          // Just cleared the box for all consortium providers
          } else {
            this.consortiumProviders.forEach( (cp) => {
              var idx = this.mutable_filters['prov'].findIndex( p => p == cp.id)
              if (idx >= 0) this.mutable_filters['prov'].splice(idx,1);
            });
          }
        },
        // @change function for filtering/clearing all error codes
        filterAllCodes() {
          if (this.allCodes) {
            this.mutable_filters['codes'] = [];
            this.allCodes = false;
          } else {
            this.mutable_filters['codes'] = [...this.codes];
            this.allCodes = true;
          }
        },
        // @change function for filtering/clearing all providers
        updateAllProvs() {
          this.allConsoProvs = (this.allConsoProvs) ? false : true;
          // Add/Remove the "All Poviders" from options depending on whether it is on or off
          if (this.allConsoProvs) {
            this.mutable_filters['prov'] = [0];
            this.mutable_options['providers'].unshift({'id': 0, 'name':'All Consortium Providers'});
          } else {
            this.mutable_filters['prov'] = [];
            this.mutable_options['providers'].splice(this.mutable_options['providers'].findIndex(p => p.id==0),1);
          }
        },
        // @change function for filtering/clearing all institutions
        updateAllInsts() {
          this.allConsoInsts = (this.allConsoInsts) ? false : true;
          if (this.allConsoInsts && (this.is_admin || this.is_viewer)) {
            this.mutable_filters['inst'] = [0];
            this.mutable_filters['group'] = [];
            this.mutable_options['institutions'].unshift({'id': 0, 'name':'All Institutions'});
          } else {
            this.mutable_filters['inst'] = [];
            this.mutable_filters['group'] = [];
            this.mutable_options['institutions'].splice(this.mutable_options['institutions'].findIndex(ii => ii.id==0),1);
          }
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_admin', 'is_viewer', 'filter_by_fromYM', 'filter_by_toYM', 'all_filters',
                     'datatable_options']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
      consortiumProviders() {
        return this.providers.filter(p => p.inst_id==1);
      },
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','harvestlogs');
    },
    mounted() {
      // Update any null/empty filters w/ store-values
      Object.keys(this.all_filters).forEach( (key) =>  {
        if (key == 'fromYM' || key == 'toYM' || key == 'updated' || key == 'source') {
            if (this.mutable_filters[key] == null || this.mutable_filters[key] == "")
                this.mutable_filters[key] = this.all_filters[key];
        } else {
            if (typeof(this.mutable_filters[key]) != 'undefined') {
                if (this.mutable_filters[key].length == 0)
                    this.mutable_filters[key] = this.all_filters[key];
            }
        }
      });

      // Inst-filter > Group-filter, if one has a value, set the flag
      if (this.mutable_filters['inst'].length>0) {
          this.mutable_filters['group'] = [];
          this.inst_filter = 'I';
      } else if (this.mutable_filters['group'].length>0) {
          this.inst_filter = 'G';
      }

      // Set initial fitler options
      Object.keys(this.mutable_options).forEach( (key) => {
        this.mutable_options[key] = [...this[key]];
      });
      this.mutable_updated = ["Last 24 hours"];

      // Set datatable options with store-values
      Object.assign(this.mutable_dt_options, this.datatable_options);

      // Setup date-bounds for the date-selector
      if (typeof(this.bounds[0]) != 'undefined') {
        this.minYM = this.bounds[0].YM_min;
        this.maxYM = this.bounds[0].YM_max;
      }

      // Remove institution column in output if not admin or viewer
      if (!this.is_admin && !this.is_viewer) {
         this.headers.splice(this.headers.findIndex(h=>h.value == "inst_name"),1);
      }

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style scoped>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
