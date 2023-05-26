<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" type="button" @click="importForm" class="section-action">
          Import Sushi Settings
        </v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <a @click="doExport"><v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export to Excel</a>
      </v-col>
      <v-col v-if="mutable_institutions.length>1" class="d-flex px-2" cols="3">
        <v-btn small color="primary" type="button" @click="newSetting" class="section-action">
          Add a Connection
        </v-btn>
      </v-col>
      <v-col v-else class="d-flex px-2" cols="3">&nbsp;</v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
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
      <v-col v-if="mutable_filters['group']==0 && mutable_institutions.length>1" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_institutions" v-model="mutable_filters['inst']" @change="updateFilters('inst')" multiple
                  label="Institution(s)"  item-text="name" item-value="id"
        ></v-autocomplete>
      </v-col>
      <v-col v-if="is_admin && mutable_filters['inst'].length==0" class="d-flex px-2" cols="2">
        <div v-if="mutable_filters['group'] != 0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('group')"/>&nbsp;
        </div>
        <v-autocomplete :items="inst_groups" v-model="mutable_filters['group']"  @change="updateFilters('group')"
                  label="Institution Group"  item-text="name" item-value="id" hint="Limit the display to an institution group"
        ></v-autocomplete>
      </v-col>
      <v-col v-if="mutable_providers.length>0" class="d-flex px-2 align-center" cols="2">
        <div v-if="mutable_filters['prov'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-select :items="mutable_providers" v-model="mutable_filters['prov']" @change="updateFilters('prov')" multiple
                  label="Provider(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-4 align-center" cols="2">
        <div v-if="mutable_filters['harv_stat'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('harv_stat')"/>&nbsp;
        </div>
        <v-select :items="statuses" v-model="mutable_filters['harv_stat']" @change="updateFilters('harv_stat')" multiple
                  label="Harvest Status"
        ></v-select> &nbsp;
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_settings" :loading="loading" show-select
                  item-key="id" :options="mutable_options" @update:options="updateOptions"
                  :footer-props="footer_props" :search="search" :key="'setdt_'+dtKey">
      <template v-slot:item.inst_name="{ item }">
         <span v-if="item.institution.is_active">
           <a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a>
         </span>
         <span v-else class="isInactive" @click="goEditInst(item.inst_id)">{{ item.inst_name }}</span>
      </template>
      <template v-slot:item.prov_name="{ item }">
        <span v-if="item.provider.is_active">
          <a :href="'/providers/'+item.prov_id">{{ item.prov_name }}</a>
        </span>
        <span v-else class="isInactive" @click="goEditProv(item.prov_id)">{{ item.prov_name }}</span>
      </template>
      <template v-slot:item.status="{ item }">
        <span v-if="item.status=='Enabled'"><v-icon large color="green" title="Enabled">mdi-toggle-switch</v-icon></span>
        <span v-if="item.status=='Disabled'"><v-icon large color="red" title="Disabled">mdi-toggle-switch-off</v-icon></span>
        <span v-if="item.status=='Incomplete'">
          <v-icon large color="red" title="Incomplete">mdi-toggle-switch-off</v-icon>
        </span>
        <span v-if="item.status=='Suspended'">
          <v-icon large color="gray" title="Suspended">mdi-toggle-switch-outline</v-icon>
        </span>
      </template>
      <template v-slot:item.customer_id="{ item }">
        <span v-if="item.customer_id=='-missing-'" class="Incomplete">missing+required</span>
        <span v-else>{{ item.customer_id }}</span>
      </template>
      <template v-slot:item.requestor_id="{ item }">
        <span v-if="item.requestor_id=='-missing-'" class="Incomplete">missing+required</span>
        <span v-else>{{ item.requestor_id }}</span>
      </template>
      <template v-slot:item.API_key="{ item }">
        <span v-if="item.API_key=='-missing-'" class="Incomplete">missing+required</span>
        <span v-else>{{ item.API_key }}</span>
      </template>
      <template v-slot:item.extra_args="{ item }">
        <span v-if="item.extra_args=='-missing-'" class="Incomplete">missing+required</span>
        <span v-else>{{ item.extra_args }}</span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-icon title="Edit Sushi Settings" @click="editSetting(item)">mdi-cog-outline</v-icon>
          &nbsp; &nbsp;
          <v-icon title="Delete connection" @click="destroy(item)">mdi-trash-can-outline</v-icon>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="importDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Sushi Settings imports function exclusively as Updates. No existing settings
              will be deleted.</strong>
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
    <v-dialog v-model="sushiDialog" persistent content-class="ccplus-dialog">
      <sushi-dialog :dtype="sushiDialogType" :institutions="sushi_insts" :providers="sushi_provs" :setting="current_setting"
                    :all_settings="mutable_settings" @sushi-done="sushiDialogDone" :key="sdKey"
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
                filters: { type:Object, default: () => {} },
                unset: { type:Array, default: () => [] },
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
                sushiDialog: false,
                csv_upload: null,
                mutable_settings: [],
                mutable_options: {},
                mutable_filters: {inst: [], group: 0, prov: [], harv_stat: []},
                statuses: ['Enabled','Disabled','Suspended','Incomplete'],
                mutable_institutions: [ ...this.institutions ],
                mutable_providers: [ ...this.providers ],
                mutable_unset: [...this.unset],
                loading: true,
                connectors: [],
                sushi_insts: [],
                sushi_provs: [],
                current_setting: {},
                sushiDialogType: '',
                // Actual headers array is built from these in mounted()
                header_fields: [
                  { label: 'Status', name: 'status' },
                  { label: 'Institution ', name: 'institution.name' },
                  { label: 'Provider ', name: 'provider.name' },
                  { label: '', name: 'customer_id' },
                  { label: '', name: 'requestor_id' },
                  { label: '', name: 'API_key' },
                  { label: '', name: 'extra_args' },
                  { label: ' ', name: 'action', sortable: false },
                ],
                headers: [],
                footer_props: { 'items-per-page-options': [10,50,100,-1] },
                bulk_actions: [ 'Enable', 'Disable', 'Suspend', 'Delete' ],
                bulkAction: null,
                selectedRows: [],
                dtKey: 1,
                sdKey: 1,
                form: new window.Form({
                    inst_id: null,
                    prov_id: null,
                    customer_id: '',
                    requestor_id: '',
                    API_key: '',
                    extra_args: '',
                    status: 'Enabled'
				        }),
            }
        },
        methods: {
          importForm () {
              this.csv_upload = null;
              this.importDialog = true;
          },
          doExport () {
              let url = "/sushi-export";
              if (this.mutable_filters['inst'].length > 0 || this.mutable_filters['prov'].length > 0 ||
                  this.mutable_filters['group'] != 0) {
                  url += "?filters="+JSON.stringify(this.mutable_filters);
              }
              window.location.assign(url);
          },
          updateFilters(filter) {
              this.$store.dispatch('updateAllFilters',this.mutable_filters);
              this.updateSettings();
          },
          clearFilter(filter) {
              this.mutable_filters[filter] = (filter == 'group') ? 0 : [];
              this.$store.dispatch('updateAllFilters',this.mutable_filters);
              this.updateSettings();
          },
          updateSettings() {
              this.success = "";
              this.failure = "";
              this.loading = true;
              let _filters = JSON.stringify(this.mutable_filters);
              axios.get("/sushisettings?json=1&filters="+_filters)
                   .then((response) => {
                       this.connectors = [ ...response.data.connectors ];
                       this.mutable_settings = [ ...response.data.settings ];
                       this.updateHeaders();
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
              formData.append('inst_id', this.prov_id);
              axios.post('/sushisettings/import', formData, {
                      headers: {
                          'Content-Type': 'multipart/form-data'
                      }
                    })
                   .then( (response) => {
                       if (response.data.result) {
                           // Load settings
                           this.updateSettings();
                           this.success = response.data.msg;
                       } else {
                           this.failure = response.data.msg;
                       }
                   });
              this.importDialog = false;
          },
          processBulk() {
              this.success = "";
              this.failure = "";
              let msg = "Bulk processing will process each requested setting sequentially.<br><br>";
              if (this.bulkAction == 'Enable') {
                  msg += "Enabling the selected setting(s) will cause them to be added to the harvesting queue";
                  msg += " according to the harvest day defined for the provider(s).";
              } else if (this.bulkAction == 'Disable' || this.bulkAction == 'Suspend') {
                  msg += (this.bulkAction == 'Disable') ? "Disabling" : "Suspending";
                  msg += " the selected setting(s) will leave the attempts counter intact, and will";
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
                                  this.mutable_settings.splice(this.mutable_settings.findIndex(h=>h.id == setting.id),1);
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
                      var new_status = (this.bulkAction=='Suspend') ? 'Suspended' : this.bulkAction+'d';
                      this.selectedRows.forEach( (setting) => {
                          axios.post('/sushisettings-update', {
                            inst_id: setting.inst_id,
                            prov_id: setting.prov_id,
                            status: new_status
                          })
                          .then( (response) => {
                              if (response.data.result) {
                                  var _idx = this.mutable_settings.findIndex(h=>h.id == setting.id);
                                  this.mutable_settings[_idx].status = response.data.setting.status;
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
              this.sushi_provs = [ ...this.providers ];
              this.sushi_insts = [ ...this.institutions ];
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
                     this.mutable_settings.splice(this.mutable_settings.findIndex(s=> s.id == setting.id),1);
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
          sushiDialogDone ({ result, msg, setting }) {
              this.success = '';
              this.failure = '';
              if (result == 'Created') {
                  this.success = msg;
                  this.mutable_settings.push(setting);
                  this.mutable_settings.sort((a,b) => {
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
                  this.mutable_settings[this.mutable_settings.findIndex(s => s.id == setting.id)] = setting;
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
          ...mapGetters(['all_filters','page_name','is_admin','is_manager']),
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
          // Apply any defined prop-based filters (and overwrite existing store values)
          if (typeof(this.all_filters) != 'undefined') {
              Object.assign(this.mutable_filters, this.all_filters);
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
                    this.mutable_filters[key] = this.filters[key];
                }
              }
            });

            // Update store and apply filters if some have been set
            if (count>0) this.$store.dispatch('updateAllFilters',this.mutable_filters);
          }

          // Set datatable options with store-values
          Object.assign(this.mutable_options, this.datatable_options);

          // Load settings and update column headers
          this.updateSettings();

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
  color: #dd0000;
  font-style: italic;
}
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
</style>
