<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" type="button" @click="importForm" class="section-action">
          Import Sushi Settings
        </v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <a @click="exportDialog=true;"><v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export to Excel</a>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" type="button" @click="newSetting" class="section-action">
          Add a Connection
        </v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details clearable
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row class="d-flex pa-1 align-center" no-gutters>
      <v-col class="d-flex px-2" cols="2">
        <v-select :items='bulk_actions' v-model='bulkAction' label="Bulk Actions" @change="processBulk()"
                  :disabled='selectedRows.length==0'
        ></v-select>
      </v-col>
      <v-col v-if="selectedRows.length>0" class="d-flex px-4 align-center" cols="2">
        <span class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
      </v-col>
      <v-col v-else class="d-flex" cols="2">&nbsp;</v-col>
      <v-col v-if="showInstFilter" class="d-flex px-2 align-center" cols="2">
        <div v-if="filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-autocomplete :items="filter_options['inst']" v-model="filters['inst']" @change="updateInstFilter()" multiple
                  label="Institution(s)"  item-text="name" item-value="id"
        ></v-autocomplete>
      </v-col>
      <v-col v-if="showGroupFilter" class="d-flex px-2 align-center" cols="2">
        <div v-if="filters['group'] != 0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('group')"/>&nbsp;
        </div>
        <v-autocomplete :items="filter_options['group']" v-model="filters['group']"  @change="updateInstFilter()"
                  label="Institution Group"  item-text="name" item-value="id" hint="Limit the display to an institution group"
        ></v-autocomplete>
      </v-col>
      <v-col v-if="is_admin && inst_context>1 && filter_options['prov'].length>0" class="d-flex px-2 align-center" cols="2">
        <div v-if="filters['inst_prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst_prov')"/>&nbsp;
        </div>
        <v-autocomplete :items="filter_options['prov']" v-model="filters['inst_prov']" label="Provider(s)" item-text="name"
                        item-value="conso_id" @change="updateFilters('inst_prov')" multiple
        ></v-autocomplete>
      </v-col>
      <v-col v-if="is_admin && inst_context<=1 && filter_options['prov'].length>0" class="d-flex px-2 align-center" cols="2">
        <div v-if="filters['global_prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('global_prov')"/>&nbsp;
        </div>
        <v-autocomplete :items="filter_options['prov']" v-model="filters['global_prov']" label="Provider(s)" item-text="name"
                        item-value="id" @change="updateFilters('global_prov')" multiple
        ></v-autocomplete>
      </v-col>
      <v-col class="d-flex px-4 align-center" cols="2">
        <div v-if="filters['harv_stat'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('harv_stat')"/>&nbsp;
        </div>
        <v-select :items="filter_options['harv_stat']" v-model="filters['harv_stat']" @change="updateFilters('harv_stat')"
                  multiple label="Harvest Status"
        ></v-select> &nbsp;
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="filtered_settings" :loading="loading" show-select
                  item-key="id" :options="mutable_options" @update:options="updateOptions"
                  :footer-props="footer_props" :search="search" :key="'setdt_'+dtKey">
      <template v-slot:item.institution.name="{ item }">
        <span v-if="item.institution.is_active==0" class="isInactive">{{ item.institution.name }}</span>
        <span v-else>{{ item.institution.name }}</span>
      </template>
      <template v-slot:item.provider.name="{ item }">
        <span v-if="item.provider.inst_id==1">
          <v-icon title="Consortium Provider">mdi-account-multiple</v-icon>&nbsp;
        </span>
        <span v-if="item.provider.is_active==0" class="isInactive">{{ item.provider.name }}</span>
        <span v-else>{{ item.provider.name }}</span>
      </template>
      <template v-slot:item.status="{ item }">
        <span v-if="item.status=='Enabled'"><v-icon large color="green" title="Enabled">mdi-toggle-switch</v-icon></span>
        <span v-if="item.status=='Disabled'"><v-icon large color="red" title="Disabled">mdi-toggle-switch-off</v-icon></span>
        <span v-if="item.status=='Incomplete'">
          <v-icon large color="orange" title="Incomplete">mdi-toggle-switch-off</v-icon>
        </span>
        <span v-if="item.status=='Suspended'">
          <v-icon large color="gray" title="Suspended">mdi-toggle-switch-outline</v-icon>
        </span>
      </template>
      <template v-slot:item.customer_id="{ item }">
        <span v-if="item.customer_id=='-missing-'" class="Incomplete">required</span>
        <span v-else>{{ item.customer_id }}</span>
      </template>
      <template v-slot:item.requestor_id="{ item }">
        <span v-if="item.requestor_id=='-missing-'" class="Incomplete">required</span>
        <span v-else>{{ item.requestor_id }}</span>
      </template>
      <template v-slot:item.api_key="{ item }">
        <span v-if="item.api_key=='-missing-'" class="Incomplete">required</span>
        <span v-else>{{ item.api_key }}</span>
      </template>
      <template v-slot:item.extra_args="{ item }">
        <span v-if="item.extra_args=='-missing-'" class="Incomplete">required</span>
        <span v-else>{{ item.extra_args }}</span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-icon title="Manual Harvest in new tab" @click="goHarvest(item)">mdi-open-in-new</v-icon>
          &nbsp; &nbsp;
          <v-icon title="Edit Sushi Settings" @click="editSetting(item)">mdi-cog-outline</v-icon>
          &nbsp; &nbsp;
          <v-icon title="Delete connection" @click="destroy(item)">mdi-trash-can-outline</v-icon>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="importDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Sushi Settings imports function exclusively as Updates. Existing settings for
              provider-institution pairs not included will be preserved.</strong>
            </p>
            <p>
              Imports will overwrite existing settings whenever a match for an Institution-ID and Provider-ID are
              found in the import file. If no setting exists for a given valid provider-institution pair, a new
              setting will be created and saved. Any values in columns D-H which are NULL, blank, or missing for
              a valid provider-institution pair, will result in the Default value being stored for that field.
            </p>
            <p>
              Generating an export of the existing settings FIRST will provide detailed instructions for
              importing on the "How to Import" tab and will help ensure that the desired end-state is achieved.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-col class="d-flex">
            <v-btn small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="exportDialog" max-width="600px">
      <v-card>
        <v-card-title>Export Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <p>
              The records to be exported depend on the display context and values defined for filters in the user interface.
              <strong>In order to retrieve all records, all filters must be cleared first.</strong>
            </p>
            <p>
              <strong>Note:&nbsp; By default, Sushi Settings exports will only include institutions and providers
                with defined connections. Enabling instution-provider pairs with missing credentials will create
                an export file containing all Active institution-provider pairs. Connection settings will be
                labelled where required or missing.</strong>
            </p>
            <v-checkbox v-model="export_missing" label="Include Connected Institution-Provider pairs with missing credentials?"
                        dense
            ></v-checkbox>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-col class="d-flex">
            <v-btn small color="primary" type="submit" @click="exportSubmit">Run Export</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn small type="button" color="primary" @click="exportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="sushiDialog" content-class="ccplus-dialog">
      <sushi-dialog :dtype="sushiDialogType" :institutions="sushi_insts" :providers="sushi_provs" :setting="current_setting"
                    :all_settings="all_settings" @sushi-done="sushiDialogDone" :key="sdKey"
      ></sushi-dialog>
    </v-dialog>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    import axios from 'axios';
    window.Form = Form;

    export default {
        props: {
                providers: { type:Array, default: () => [] },
                institutions: { type:Array, default: () => [] },
                inst_groups: { type:Array, default: () => [] },
                unset: { type:Array, default: () => [] },
                inst_context: { type: Number, default: 1 }
               },
        data() {
            return {
                success: '',
                failure: '',
                testData: '',
                testStatus: '',
                search: '',
                showTest: false,
                importDialog: false,
                exportDialog: false,
                sushiDialog: false,
                csv_upload: null,
                export_missing: false,
                all_settings: [],
                mutable_options: {},
                filters: {inst: [], group: 0, global_prov: [], inst_prov: [], harv_stat: []},
                statuses: ['Enabled','Disabled','Suspended','Incomplete'],
                filter_options: {'inst': [], 'prov': [], 'group': [], 'harv_stat': []},
                limit_inst_ids: [],
                mutable_unset: [...this.unset ],
                loading: true,
                connectors: [],
                sushi_insts: [],
                sushi_provs: [],
                current_setting: {},
                sushiDialogType: '',
                // Actual headers array is built from these in mounted()
                header_fields: [
                  { label: 'Status', name: 'status' },
                  { label: 'Institution', name: 'institution.name' },
                  { label: 'Provider', name: 'provider.name' },
                  { label: '', name: 'customer_id' },
                  { label: '', name: 'requestor_id' },
                  { label: '', name: 'api_key' },
                  { label: '', name: 'extra_args' },
                  { label: ' ', name: 'action', sortable: false },
                ],
                headers: [],
                footer_props: { 'items-per-page-options': [10,50,100,-1] },
                bulk_actions: [ 'Enable', 'Disable', 'Delete' ],
                bulkAction: null,
                selectedRows: [],
                dtKey: 1,
                sdKey: 1,
                form: new window.Form({
                    inst_id: null,
                    prov_id: null,
                    customer_id: '',
                    requestor_id: '',
                    api_key: '',
                    extra_args: '',
                    status: 'Enabled'
				        }),
            }
        },
        watch: {
          all_providers: {
             handler () {
               this.filter_options['prov'].sort((a,b) => {
                 if ( a.name < b.name ) return -1;
                 if ( a.name > b.name ) return 1;
                 return 0;
               });
             },
             deep: true
           },
        },
        methods: {
          importForm () {
              this.csv_upload = null;
              this.importDialog = true;
          },
          // Called onChange inst or group filter
          updateInstFilter() {
              if (this.inst_context != 1) return;
              let filter = 'inst';
              if (this.filters['inst'].length > 0) {
                  this.limit_inst_ids = [ ...this.filters['inst'] ];
              } else if (this.filters['group'] > 0) {
                  filter = 'group';
                  this.limit_inst_ids = [];
                  let group = this.inst_groups.find(g => g.id == this.filters['group']);
                  if (typeof(group) != 'undefined') {
                      group.institutions.forEach( (inst) => { this.limit_inst_ids.push(inst.id) } );
                  }
              } else {
                  this.limit_inst_ids = [];
              }
              this.$store.dispatch('updateAllFilters',this.filters);
              this.updateFilterOptions(filter);
          },
          // Called onChange inst_prov, global_prov or harv_stat filter
          updateFilters(filter) {
              this.$store.dispatch('updateAllFilters',this.filters);
              this.updateFilterOptions(filter);
          },
          clearFilter(filter) {
              this.filters[filter] = (filter == 'group') ? 0 : [];
              if (filter == 'inst' || filter == 'group') this.limit_inst_ids = [];
              this.$store.dispatch('updateAllFilters',this.filters);
              this.updateFilterOptions(filter);
              this.dtKey += 1;           // re-render of the datatable
          },
          // Update inst, provider, and group filter options
          updateFilterOptions(changed_filter) {
              // If no active filters, reset everything
              if (this.filters['harv_stat'].length==0 && this.limit_inst_ids.length==0 && this.context_prov_filter.length==0) {
                  this.filter_options['inst'] = [...this.institutions];
                  this.filter_options['prov'] = [...this.contextual_providers];
                  this.filter_options['group'] = [...this.inst_groups];
                  this.filter_options['harv_stat'] = [...this.statuses];
                  return;
              }
              // Set flag if changed_filter was just reset (so we can reset the options)
              let just_cleared = ( ( (changed_filter == 'inst' || changed_filter == 'group') && this.limit_inst_ids.length==0 ) ||
                                   ( changed_filter == 'harv_stat' && this.filter_options['harv_stat'].length==0 ) ||
                                   ( (changed_filter == 'inst_prov' || changed_filter == 'global_prov') &&
                                     this.context_prov_filter.length==0) );
              // Update filter options (skip changed filter if it is not cleared)
              // Filter to what is found + what is set in the filter already, starting with the inst/group filters
              if ( this.inst_context == 1 ) {
                  if ( just_cleared || (changed_filter != 'inst' && changed_filter != 'group') ) {
                    let inst_ids = this.filtered_settings.map(s => s.inst_id);
                    this.filter_options['inst'] = this.institutions.filter(ii => (inst_ids.includes(ii.id) ||
                                                                                  this.filters['inst'].includes(ii.id)));
                    var group_ids = [];
                    this.inst_groups.forEach( (gg) => {
                      gg.institutions.forEach( (ii) => {
                        if ( inst_ids.includes(ii.id) && !group_ids.includes(gg.id)) group_ids.push(gg.id);
                      });
                    });
                    this.filter_options['group'] = this.inst_groups.filter(g => (group_ids.includes(g.id) ||
                                                                                 this.filters['group'] == g.id));
                  }
              }

              // rebuild providers
              if (just_cleared || !changed_filter.includes('prov')) {
                let prov_ids = this.filtered_settings.map(s => s.prov_id);
                this.filter_options['prov'] = this.providers.filter(p => (prov_ids.includes(p.conso_id) ||
                                                                          this.context_prov_filter.includes(p.id)));
              }
              // rebuild status
              if (just_cleared || changed_filter != 'harv_stat') {
                let status_vals = this.filtered_settings.map(s => s.status);
                let values = [... new Set(status_vals)];
                this.filter_options['harv_stat'] = this.statuses.filter(s => (values.includes(s) ||
                                                                              this.filters['harv_stat'].includes(s)));
              }
          },
          getSettings() {
            let url = "/sushisettings?json=1";
            if (this.inst_context > 1) url+="&context="+this.inst_context;
            axios.get(url)
                 .then((response) => {
                   this.connectors = [ ...response.data.connectors ];
                   this.all_settings = [ ...response.data.settings ];
                   this.updateHeaders();
                   this.updateFilterOptions('ALL');
                 })
                 .catch(err => console.log(err));
            this.loading = false;
            this.dtKey += 1;           // re-render of the datatable
          },
          // Build/Re-build DataTable headers array based on the provider connectors
          updateHeaders() {
              this.headers = [];
              this.header_fields.forEach((fld) => {
                  // Connection fields are setup in "header_fields" as names without labels
                  if (fld.label == '' && fld.name != '') {
                      // any provider using the field means we make a column for it
                      let cnx = this.connectors.find(c => c.name == fld.name);
                      if (typeof(cnx) != 'undefined') {
                          this.headers.push({ text: cnx.label, value: cnx.name});
                      }
                  } else {
                      this.headers.push({ text: fld.label, value: fld.name });
                  }
              });
          },
          updateOptions(options) {
              Object.keys(this.mutable_options).forEach( (key) =>  {
                  if (options[key] !== this.mutable_options[key]) {
                      this.mutable_options[key] = options[key];
                  }
              });
              this.$store.dispatch('updateDatatableOptions',this.mutable_options);
          },
          importSubmit (event) {
              this.success = '';
              if (this.csv_upload==null) {
                  this.failure = 'A CSV import file is required';
                  return;
              }
              this.failure = '';
              let formData = new FormData();
              formData.append('csvfile', this.csv_upload);
              axios.post('/sushisettings/import', formData, {
                      headers: {
                          'Content-Type': 'multipart/form-data'
                      }
                    })
                   .then( (response) => {
                       if (response.data.result) {
                           // Reload all settings
                           this.getSettings();
                           this.success = response.data.msg;
                       } else {
                           this.failure = response.data.msg;
                       }
                   });
              this.importDialog = false;
          },
          exportSubmit (event) {
              this.success = '';
              this.failure = '';
              let url = "/sushi-export?export_missing="+this.export_missing;
              if (this.filters['inst'].length > 0 || this.filters['group'] != 0 ||
                  this.filters['global_prov'].length > 0 || this.filters['inst_prov'].length > 0) {
                  url += "&context="+this.inst_context+"&filters="+JSON.stringify(this.filters);
              }
              window.location.assign(url);
              this.exportDialog = false;
          },
          processBulk() {
              this.success = "";
              this.failure = "";
              let msg = "Bulk processing will process each requested setting sequentially.<br><br>";
              if (this.bulkAction == 'Enable') {
                  msg += "Enabling the selected setting(s) will cause them to be added to the harvesting queue";
                  msg += " according to the harvest day defined for the provider(s).";
              } else if (this.bulkAction == 'Disable') {
                  msg += " Disabling the selected setting(s) will leave the attempts counter intact, and will";
                  msg += " prevent future harvesting attempts. Any queued harvests related to the settings";
                  msg += " will be cancelled; harvests that are 'Active', or 'Pending' will not be changed.";
              } else if (this.bulkAction == 'Delete') {
                  msg += "Deleting the selected settings records is not reversible! No harvested data will be removed or";
                  msg += " changed. <br><br><strong>NOTE:</strong> all harvest log records connected to these settings";
                  msg += " will also be deleted!";
              } else {
                  this.failure = "Unrecognized Bulk Action in processBulk!";
                  return;
              }
              Swal.fire({
                title: 'Are you sure?', html: msg, icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, Proceed!'
              })
              .then((result) => {
                if (result.value) {
                  this.success = "Working...";
                  if (this.bulkAction == 'Delete') {
                      for (let idx=0; idx<this.selectedRows.length; idx++) {
                        var setting=this.selectedRows[idx];
                        axios.delete('/sushisettings/'+setting.id)
                          .then( (response) => {
                              if (response.data.result) {
                                  this.all_settings.splice(this.all_settings.findIndex(h=>h.id == setting.id),1);
                                  this.selectedRows.splice(idx,1);
                              } else {
                                  this.success = '';
                                  this.failure = response.data.msg;
                                  return false;
                              }
                          }).catch({});
                      }
                      this.success = "Selected settings successfully deleted.";
                  } else {
                      var new_status = this.bulkAction+'d';
                      this.selectedRows.forEach( (setting) => {
                          axios.post('/sushisettings-update', {
                            inst_id: setting.inst_id,
                            prov_id: setting.prov_id,
                            status: new_status
                          })
                          .then( (response) => {
                              if (response.data.result) {
                                  var _idx = this.all_settings.findIndex(h=>h.id == setting.id);
                                  this.all_settings[_idx].status = response.data.setting.status;
                              } else {
                                  this.success = '';
                                  this.failure = response.data.msg;
                                  return false;
                              }
                          }).catch(error => {});
                      });
                      this.success = "Selected settings successfully updated.";
                  }
                }
                this.bulkAction = '';
                this.dtKey += 1;           // update the datatable
                return true;
            })
            .catch({});
          },
          editSetting (item) {
              this.sdKey += 1;
              this.current_setting = item;
              this.sushi_provs = [];
              this.sushi_insts = [];
              this.sushiDialogType = 'edit';
              this.sushiDialog = true;
          },
          newSetting() {
              this.sdKey += 1;
              this.current_setting = {};
              this.sushi_provs = (this.inst_context==1)
                                 ? this.providers.filter(p => p.conso_id!=null && p.inst_id==1)
                                 : this.providers.filter(
                                     p => p.conso_id!=null && (p.inst_id==this.context || p.inst_id==1)
                                   );
              this.sushi_insts = [ ...this.filter_options['inst'] ];
              this.sushiDialogType = 'create';
              this.sushiDialog = true;
          },
          destroy (setting) {
              let msg = "Deleting this setting is not reversible!<br /><br />No harvested data will be removed";
              msg += " or changed. <br><br><strong>NOTE:</strong> all harvest log records connected to this";
              msg += " setting will also be deleted!";
              Swal.fire({
                title: 'Are you sure?', html: msg, icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
              }).then((result) => {
                if (result.value) {
                    axios.delete('/sushisettings/'+setting.id)
                         .then( (response) => {
                             if (response.data.result) {
                                 this.failure = '';
                                 this.success = response.data.msg;
                             } else {
                                 this.success = '';
                                 this.failure = response.data.msg;
                             }
                         })
                         .catch({});
                     // Add the entry to the "unset" list and res-sort it
                     this.mutable_unset.push({'id': setting.prov_id, 'name': setting.provider.name});
                     this.mutable_unset.sort((a,b) => {
                       if ( a.name < b.name ) return -1;
                       if ( a.name > b.name ) return 1;
                       return 0;
                     });
                     // Remove the setting from the "set" list
                     this.all_settings.splice(this.all_settings.findIndex(s=> s.id == setting.id),1);
                     this.form.prov_id = 0;
                }
              })
              .catch({});
          },
          goEditInst (instId) {
              window.location.assign('/institutions/'+instId);
          },
          goEditProv (provId) {
              window.location.assign('/providers/'+provId);
          },
          goHarvest(setting) {
              window.open("/harvests/create?inst="+setting.inst_id+"&prov="+setting.prov_id, "_blank");
          },
          sushiDialogDone ({ result, msg, setting }) {
              this.success = '';
              this.failure = '';
              if (result == 'Created') {
                  this.success = msg;
                  this.all_settings.push(setting);
                  this.all_settings.sort((a,b) => {
                    if ( a.provider.name < b.provider.name ) return -1;
                    if ( a.provider.name > b.provider.name ) return 1;
                    return 0;
                  });
                  // Remove the provider from the appropriate unset array
                  this.mutable_unset.splice(this.mutable_unset.findIndex(p => p.id==setting.provider.id),1);
                  // Check provider connectors to see if a new connector was just enabled
                  let new_cnx = false;
                  setting.provider.connectors.forEach( (cnx) => {
                      let existing_cnx = this.connectors.find(c => c.name == cnx.name);
                      if (typeof(existing_cnx) == 'undefined') {
                        this.connectors.push(cnx);
                        new_cnx = true;
                      }
                  });
                  // If new connector enabled, rebuild headers
                  if (new_cnx) this.updateHeaders();
                  this.dtKey += 1;
              } else if (result == 'Updated') {
                  this.all_settings[this.all_settings.findIndex(s => s.id == setting.id)] = setting;
                  this.dtKey += 1;
              } else if (result == 'Fail') {
                  this.failure = msg;
              } else if (result != 'Cancel') {
                  this.failure = 'Unexpected Result returned from sushiDialog - programming error!';
              }
              this.sushi_provs = [{ 'global_prov': {'connectors': []} }];
              this.sushiDialog = false;
          },
          isEmpty(obj) {
            for (var i in obj) return false;
            return true;
          }
        },
        computed: {
          ...mapGetters(['all_filters','is_admin','is_manager','datatable_options']),
          showInstFilter() {
            return this.filters['group']==0 &&
                   (this.inst_context==1 || this.filter_options['inst'].length>1);
          },
          showGroupFilter() {
            return (this.is_admin && this.inst_context==1 && this.filters['inst'].length==0);
          },
          all_providers() { return this.providers; },
          contextual_providers() {
            if (this.inst_context==1) {
              return this.providers.filter(p => (p.conso_id!=null && p.inst_id==1));
            } else if (this.inst_context>0) {
              return this.providers.filter(p => (p.conso_id!=null && (p.inst_id==1 || p.inst_id==this.inst_context)));
            } else {
              return [ ...this.providers];
            }
          },
          context_prov_filter: function() {
            return (this.inst_context==1) ? this.filters['global_prov'] : this.filters['inst_prov'];
          },
          filtered_settings: function() {
            // Inst or group filter is on
            if (this.limit_inst_ids.length > 0 ) {
              if (this.context_prov_filter.length>0) {
                // Inst filter on, provider filter on
                if (this.filters['harv_stat'].length>0) {
                  return this.all_settings.filter(s => this.limit_inst_ids.includes(s.inst_id) &&
                                          ((this.inst_context==1 && this.context_prov_filter.includes(s.provider.global_id)) ||
                                           (this.inst_context>1 && this.context_prov_filter.includes(s.prov_id)) ) &&
                                          s.status==this.filters['harv_stat']);
                } else {
                  return this.all_settings.filter(s => this.limit_inst_ids.includes(s.inst_id) &&
                                          ((this.inst_context==1 && this.context_prov_filter.includes(s.provider.global_id)) ||
                                           (this.inst_context>1 && this.context_prov_filter.includes(s.prov_id)) ) );
                }
              // Inst filter on, No provider filter
              } else {
                if (this.filters['harv_stat'].length>0) {
                  return this.all_settings.filter(s => this.limit_inst_ids.includes(s.inst_id) &&
                                                       s.status==this.filters['harv_stat']);
                } else {
                  return this.all_settings.filter(s => this.limit_inst_ids.includes(s.inst_id));
                }
              }
            // No Inst-filter
            } else {
              if (this.context_prov_filter.length>0) {
                // Inst filter off, provider filter on
                if (this.filters['harv_stat'].length>0) {
                  return this.all_settings.filter(s => ((this.inst_context==1 &&
                                                         this.context_prov_filter.includes(s.provider.global_id)) ||
                                                        (this.inst_context>1 && this.context_prov_filter.includes(s.prov_id))) &&
                                                       s.status==this.filters['harv_stat']);
                } else {
                  return this.all_settings.filter(s => (this.inst_context==1 &&
                                                        this.context_prov_filter.includes(s.provider.global_id)) ||
                                                       (this.inst_context>1 && this.context_prov_filter.includes(s.prov_id)));
                }
              // No inst filter, No provider filter
              } else {
                if (this.filters['harv_stat'].length>0) {
                  return this.all_settings.filter(s => s.status==this.filters['harv_stat']);
                } else {
                  return [ ...this.all_settings ];
                }
              }
            }
          }
        },
        beforeCreate() {
          // Load existing store data
          this.$store.commit('initialiseStore');
          // Subscribe to store updates
          this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
        },
        beforeMount() {
          // Set page name in the store
          this.$store.dispatch('updatePageName','sushi');
        },
        mounted() {
          // Default filter options to everything
          this.filter_options.harv_stat = [...this.statuses];
          if (this.inst_context == 1) {
            this.filter_options.inst = [...this.institutions];
            this.filter_options.group = [...this.inst_groups];
          } else {
            this.filter_options.inst = [this.institutions[0]];
            this.filter_options.group = [];
            this.limit_inst_ids = [this.institutions[0].id];
          }
          this.filter_options['prov'] = [...this.contextual_providers];

          // Apply any existing filter values from the datastore and update the options arrays as-needed
          if (typeof(this.all_filters) != 'undefined') {
              Object.assign(this.filters, this.all_filters);
          }
          if ( !this.isEmpty(this.filters) ) {
            var count = 0;
            Object.keys(this.filters).forEach( (key) =>  {
              if (this.filters[key] != null) {
                let count_it = false;
                if ( key == 'group') {
                    if ( this.filters['group'] != 0) count_it = true;
                } else {
                    if (this.filters[key].length>0) count_it = true;
                }
                if (count_it) {
                    count++;
                    this.filters[key] = this.filters[key];
                }
              }
            });

            // Update store and apply filters if some have been set
            if (count>0) this.$store.dispatch('updateAllFilters',this.filters);
          }

          // Set datatable options with store-values
          Object.assign(this.mutable_options, this.datatable_options);

          // If we're viewing settings for a single institution, remove the bnstitution column from the datatable
          if (this.institutions.length == 1) {
            this.header_fields.splice(this.header_fields.findIndex( h => h.label == 'Institution'),1);
          }

          // Load settings, update column headers and filter options
          this.getSettings();

          // Subscribe to store updates
          this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

          console.log('SushiSettings Datatable Component mounted.');
      }
    }
</script>

<style scoped>
.Enabled { color: #00dd00; }
.Disabled { color: #dd0000; }
.Suspended {
  color: #999999;
  font-style: italic;
}
.Incomplete {
  color: #ff9900;
  font-style: italic;
}
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
</style>
