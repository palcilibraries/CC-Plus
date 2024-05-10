<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="createForm()">Add a Platform</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="enableImportForm">Import Platforms</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <a @click="doExport">
          <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export platforms to Excel
        </a>
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
      <v-col class="d-flex px-2 align-center" cols="2">
        <v-select :items="status_options" v-model="mutable_filters['stat']" @change="updateFilters('stat')"
                  label="Limit by Status"
        ></v-select> &nbsp;
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_providers" :loading="loading" show-select
                  item-key="id" :options="mutable_options" @update:options="updateOptions" :search="search" :key="dtKey"
                  :footer-props="footer_props">
      <template v-slot:item.status="{ item }">
        <span v-if="item.status=='Active'">
          <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
        </span>
        <span v-else-if="item.status=='Inactive'">
          <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
        </span>
      </template>
      <template v-slot:item.name="{ item }">
        <span v-if="item.refreshable==0">
          <v-icon title="COUNTER API Updates Disabled">mdi-sync-off</v-icon>&nbsp;
        </span>
        {{ item.name }}
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn icon @click="goURL('https://registry.projectcounter.org/platform/'+item.registry_id)">
            <v-icon title="Open Registry Details">mdi-open-in-new</v-icon>
          </v-btn>
          <v-btn icon @click="editForm(item.id)">
            <v-icon title="Edit Platform">mdi-cog-outline</v-icon>
          </v-btn>
          <v-btn v-if="item.can_delete" icon @click="destroy(item.id)">
            <v-icon title="Delete Platform">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-else icon>
            <v-icon color="#c9c9c9">mdi-trash-can-outline</v-icon>
          </v-btn>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="providerImportDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Platforms</v-card-title>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ CSV Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Platform imports function exclusively as Updates. No existing platform records will
              be deleted.</strong>
            </p>
            <p>
              The import process overwrites existing settings whenever a match for a Platform-ID is found in column-A
              of the import file. If no existing setting is found for the specified Platform-ID, a NEW platform will
              be created with the fields specified. Platform names (column-B) must be unique. Attempting to create
              a platform (or rename one) using an existing name will be ignored.
            </p>
            <p>
              Platforms can be renamed via import by giving the ID in column-A and the replacement name in column-B.
              Be aware that the new name takes effect immediately, and will be associated with all harvested usage
              data that may have been collected using the OLD name (data is stored by the ID, not the name.)
            </p>
            <p>
              For these reasons, use caution when using this import function. Generating a Platform export FIRST will
              supply detailed instructions for importing on the "How to Import" tab. Generating a new Platform export
              AFTER an import operation is a good way to confirm that all the settings are as-desired.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="providerImportSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="providerImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="provDialog" content-class="ccplus-dialog">
        <v-container grid-list-sm>
          <v-form v-model="formValid">
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex pt-2 justify-center"><h1 align="center">{{ dialog_title }}</h1></v-col>
            </v-row>
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex px-4" cols="10">
                <v-text-field v-model="form.name" label="Platform" outlined dense></v-text-field>
              </v-col>
              <v-col class="d-flex px-4" cols="2">
                <div class="idbox">
                  <v-icon title="CC+ Platform ID">mdi-web</v-icon>&nbsp; {{ current_provider_id }}
                </div>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex px-4" cols="10">
                <v-text-field v-model="form.content_provider" label="Content Provider" outlined dense></v-text-field>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex px-4">
                <v-text-field v-model="form.server_url_r5" label="SUSHI Server URL" outlined dense></v-text-field>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex px-4" cols="6">
                <v-list dense>
                  <v-list-item class="verydense"><strong>Connection Fields</strong></v-list-item>
                  <v-list-item v-for="cnx in all_connectors" :key="cnx.name" class="verydense">
                    <v-checkbox :value="form.connector_state[cnx.name]" :key="cnx.name" :label="cnx.label"
                                v-model="form.connector_state[cnx.name]"  @change="changeConnector() "dense>
                    </v-checkbox>
                  </v-list-item>
                </v-list>
              </v-col>
              <v-col class="d-flex px-4" cols="6">
                <v-list dense>
                  <v-list-item class="verydense"><strong>Supported Reports</strong></v-list-item>
                  <v-list-item v-for="rpt in master_reports" :key="rpt.name" class="verydense">
                    <v-checkbox :value="form.report_state[rpt.name]" :key="rpt.name" :label="rpt.name"
                                v-model="form.report_state[rpt.name]" dense>
                    </v-checkbox>
                  </v-list-item>
                </v-list>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0" no-gutters>
              <v-col class="d-flex px-4" cols="8">
                <v-text-field v-model="form.platform_parm" label="Platform Parameter" outlined dense></v-text-field>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0 align-center" no-gutters>
              <v-col class="d-flex px-4">
                <v-switch v-model="form.refreshable" label="Enable COUNTER API Refresh" dense></v-switch>
              </v-col>
            </v-row>
            <v-row v-if="form.refreshable" class="d-flex ma-0" no-gutters>
              <v-col class="d-flex pl-4" cols="9">
                <v-text-field v-model="form.registry_id" label="COUNTER Registry ID" outlined dense></v-text-field>
              </v-col>
              <v-col v-if="showRefresh" class="d-flex px-2" cols="3">
                <v-btn x-small color="primary" type="button" @click="registryRefresh(null)">Registry Refresh</v-btn>
              </v-col>
            </v-row>
            <v-row class="d-flex ma-0 align-center" no-gutters>
              <v-col class="d-flex px-4" cols="4">
                <v-switch v-model="form.is_active" label="Active?" dense></v-switch>
              </v-col>
              <v-col class="d-flex px-4" cols="4">
                <v-btn x-small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                  Save Platform
                </v-btn>
              </v-col>
              <v-col class="d-flex px-4" cols="4">
                <v-btn x-small color="primary" type="button" @click="provDialog=false">Cancel</v-btn>
              </v-col>
            </v-row>
            <v-row v-if="updated_at!=null" class="d-flex ma-0" no-gutters>
              <v-col class="d-flex justify-center"><em>Last Updated: {{ updated_at }}</em></v-col>
            </v-row>
          </v-form>
        </v-container>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import Swal from 'sweetalert2';
  export default {
    props: {
            providers: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
            all_connectors: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} }
           },
    data () {
      return {
        success: '',
        failure: '',
        providerImportDialog: false,
        settingsImportDialog: false,
        provDialog: false,
        showRefresh: false,
        dialog_title: '',
        current_provider_id: null,
        current_connector_state: {},
        warnConnectors: false,
        updated_at: null,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement'],
        mutable_filters: this.filters,
        status_options: ['ALL', 'Active', 'Inactive', 'Refresh Disabled'],
        bulk_actions: [ 'Enable', 'Disable', 'Refresh Registry', 'Delete' ],
        bulkAction: null,
        selectedRows: [],
        loading: true,
        search: '',
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'Abbrev', value: 'abbrev', align: 'start' },
          { text: 'Platform Name', value: 'name', align: 'start' },
          { text: 'Content Provider', value: 'content_provider', align: 'start' },
          { text: 'Connection Count', value: 'connection_count', align: 'center' },
          { text: '', value: 'action', sortable: false },
        ],
        mutable_providers: [ ...this.providers],
        new_provider: {'id': null, 'registry_id': '', 'name': '', 'content_provider': '', 'abbrev': '', 'is_active': 1,
                       'refreshable': 0, 'report_state': {}, 'connector_state': {}, 'server_url_r5': '',
                       'platform_parm': null, 'notifications_url': ''},
        formValid: true,
        form: new window.Form({
            registry_id: '',
            name: '',
            content_provider: '',
            abbrev: '',
            is_active: 1,
            refreshable: 1,
            server_url_r5: '',
            connector_state: [],
            report_state: [],
            notifications_url: '',
            platform_parm: null,
        }),
        dayRules: [
            v => !!v || "Day of month is required",
            v => ( v && v >= 1 ) || "Day of month must be > 1",
            v => ( v && v <= 28 ) || "Day of month must be < 29",
        ],
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
      }
    },
    methods:{
        editForm (gp_id) {
            this.failure = '';
            this.success = '';
            this.dialog_title = "Edit Platform Settings";
            let _prov = this.mutable_providers.find(p => p.id == gp_id);
            this.current_provider_id = gp_id;
            this.current_connector_state = Object.assign({},_prov.connector_state);
            this.form.connector_state = Object.assign({},_prov.connector_state);
            this.form.registry_id = _prov.registry_id;
            this.form.name = _prov.name;
            this.form.content_provider = _prov.content_provider;
            this.form.abbrev = _prov.abbrev;
            this.form.is_active = _prov.is_active;
            this.form.refreshable = _prov.refreshable;
            this.form.server_url_r5 = _prov.server_url_r5;
            this.form.report_state = _prov.report_state;
            this.form.notifications_url = _prov.notifications_url;
            this.form.platform_parm = _prov.platform_parm;
            this.updated_at = _prov.updated_at;
            this.showRefresh = _prov.refreshable; // button only displays when refreshable in form AND saved provider are true
            this.providerImportDialog = false;
            this.settingsImportDialog = false;
            this.provDialog = true;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.dialog_title = "Add Platform Definition";
            this.form.registry_id = this.new_provider.registry_id;
            this.form.name = this.new_provider.name;
            this.form.content_provider = this.new_provider.content_provider;
            this.form.abbrev = this.new_provider.abbrev;
            this.form.is_active = this.new_provider.is_active;
            this.form.refreshable = this.new_provider.refreshable;
            this.form.server_url_r5 = this.new_provider.server_url_r5;
            this.form.connector_state = Object.assign({},_this.new_provider.connector_state);
            this.form.report_state = this.new_provider.report_state;
            this.form.platform_parm = this.new_provider.platform_parm;
            this.form.notifications_url = this.new_provider.notifications_url;
            this.updated_at = null;
            this.showRefresh = false;
            this.providerImportDialog = false;
            this.settingsImportDialog = false;
            this.provDialog = true;
        },
        enableImportForm () {
            this.csv_upload = null;
            this.providerImportDialog = true;
            this.settingsImportDialog = false;
            this.provDialog = false;
        },
        providerImportSubmit (event) {
            this.success = '';
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/global/providers/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response providers
                         this.mutable_providers = response.data.providers;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.providerImportDialog = false;
        },
        changeStatus(gpId, state) {
            axios.patch('/global/providers/'+gpId, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   var _idx = this.mutable_providers.findIndex(ii=>ii.id == gpId);
                   this.mutable_providers[_idx].is_active = state;
                   this.mutable_providers[_idx].status = response.data.provider.status;
                   this.$emit('change-prov', gpId);
                   this.dtKey += 1;
                 }
               })
               .catch(error => {});
        },
        // Set flag if we need to warn about connectors being turned off (happens when saving)
        changeConnector() {
          let all_match = true;
          Object.keys(this.form.connector_state).forEach( (cnx) => {
            if (this.form.connector_state[cnx] != this.current_connector_state[cnx]) all_match = false;
            if (this.current_connector_state[cnx] == true &&
                (this.form.connector_state[cnx] == null || this.form.connector_state[cnx] == false)) {
                this.warnConnectors = true;
            }
          });
          if (this.warnConnectors && all_match) this.warnConnectors = false;
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "Bulk processing will process each requested platform sequentially.<br><br>";
            if (this.bulkAction == 'Enable') {
                msg += "Enabling these platforms will make them conifgurable by instance administrators.<br/>";
                msg += "Until the required credentials are defined, however, no report retrieval will be performed<br />";
                msg += "by the CC-Plus automated harvesting system.";
            } else if (this.bulkAction == 'Disable') {
                msg += "Disabling these platforms will disable all related consortium-level platform definitions.<br/>";
                msg += "This means that no automated report harvesting will happen for these platforms, and the<br />";
                msg += "instance administrators will not be able to set or manage the status as long as they are disabled.";
            } else if (this.bulkAction == 'Refresh Registry') {
                msg += "You are about to refresh the definitions for the selected platforms to the what is kept in the<br/>";
                msg += "online COUNTER registry. This has the potential to affect harvesting, which reports are supported,<br />";
                msg += "and the credentials required to harvest the reports.";
            } else if (this.bulkAction == 'Delete') {
                msg += "CAUTION!!<br />Deleting these platform records is not reversible! Platforms with harvested";
                msg += " data will NOT be deleted.<br />";
                msg += " NOTE: ALL platform and SUSHI definitions associated with the selected platforms, across all";
                msg += " instances in this system will also be deleted!<br />";
            } else {
                this.failure = "Unrecognized Bulk Action in processBulk!";
                return;
            }
            Swal.fire({
              title: 'Are you sure?', html: msg, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, Proceed!'
            })
            .then( (result) => {
              if (result.value) {
                  this.success = "Working...";
                  if (this.bulkAction == 'Delete') {
                      this.selectedRows.forEach( (provider) => {
                          if (provider.can_delete) {
                              axios.delete('/global/providers/'+provider.id)
                                 .then( (response) => {
                                     if (response.data.result) {
                                         this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == provider.id),1);
                                     }
                                 })
                                 .catch({});
                          }
                      });
                      this.success = "Selected platforms successfully deleted.";
                  } else if (this.bulkAction == 'Refresh Registry') {
                      this.selectedRows.forEach( (provider) => {
                          this.registryRefresh(provider.id);
                      });
                      this.success = "Selected platforms successfully updated.";
                  } else {
                    let state = (this.bulkAction == 'Enable') ? 1 : 0;
                    this.selectedRows.forEach( (provider) => {
                      axios.patch('/global/providers/'+provider.id, { is_active: state })
                         .then( (response) => {
                           if (response.data.result) {
                             var _idx = this.mutable_providers.findIndex(ii=>ii.id == provider.id);
                             this.mutable_providers[_idx].is_active = state;
                             this.mutable_providers[_idx].status = response.data.provider.status;
                             this.$emit('change-prov', gpId);
                           }
                         })
                         .catch(error => {});
                      });
                      this.success = "Selected platforms successfully updated.";
                  }
              }
              this.bulkAction = '';
              this.dtKey += 1;           // update the datatable
              return true;
          })
          .catch({});
        },
        registryRefresh(gpId) {
            let provider_id = (gpId == null) ? this.current_provider_id : gpId;
            axios.post('/global/providers/registry-refresh', { id: provider_id })
                 .then( (response) => {
                   if (response.data.result) {
                     this.form.name = response.data.prov.name;
                     this.form.abbrev = response.data.prov.abbrev;
                     this.form.server_url_r5 = response.data.prov.server_url_r5;
                     this.form.connector_state = response.data.prov.connector_state;
                     this.form.report_state = response.data.prov.report_state;
                     this.form.notifications_url = response.data.prov.notifications_url;
                     var _idx = this.mutable_providers.findIndex(ii=>ii.id == gpId);
                     if (_idx > -1) {
                       this.mutable_providers[_idx].name = response.data.prov.name;
                       this.mutable_providers[_idx].content_provider = response.data.prov.content_provider;
                       this.mutable_providers[_idx].abbrev = response.data.prov.abbrev;
                       this.mutable_providers[_idx].server_url_r5 = response.data.prov.server_url_r5;
                       this.mutable_providers[_idx].connectors = response.data.prov.connectors;
                       this.mutable_providers[_idx].connector_state = response.data.prov.connector_state;
                       this.mutable_providers[_idx].report_state = response.data.prov.report_state;
                       this.mutable_providers[_idx].master_reports = response.data.prov.master_reports;
                       this.mutable_providers[_idx].notifications_url = response.data.prov.notifications_url;
                       this.$emit('change-prov', gpId);
                       this.dtKey += 1;           // update the datatable
                     }
                   } else {
                     this.success = '';
                     this.failure = response.data.msg;
                   }
           });
        },
        updateFilters() {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        clearFilter(filter) {
            this.mutable_filters[filter] = [];
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        updateRecords() {
            this.success = "";
            this.failure = "";
            this.loading = true;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/global/providers?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_providers = response.data.providers;
                 })
                 .catch(err => console.log(err));
            this.loading = false;
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            // Update existing global provider
            if (this.dialog_title == "Edit Platform Settings") {
              let idx = this.mutable_providers.findIndex(p => p.id == this.current_provider_id);
              var canDelete = this.mutable_providers[idx].can_delete;
              var connectionCount = this.mutable_providers[idx].connection_count;
              // If a required connector was turned off, popup a warning
              if (this.warnConnectors) {
                let warning_html = "One or more required connectors has been marked as no longer required. The current "+
                                   " values defined for these connectors will be cleared THROUGH ALL INSTANCES from the "+
                                   " SUSHI credentials when the platform is saved.<br />";
                warning_html += "Having good exports of the SUSHI credentials for all instances could be valuable if you find"
                warning_html += " you need to re-enable the modified connector field.";
                Swal.fire({
                  title: 'Continue to save?', html: warning_html, icon: 'warning', showCancelButton: true,
                  confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                    // Apply update
                    this.form.patch('/global/providers/'+this.current_provider_id)
                    .then( (response) => {
                        if (response.result) {
                            this.failure = '';
                            // Update the provider entry in the mutable array
                            this.mutable_providers[idx] = Object.assign({}, response.provider);
                            this.mutable_providers[idx]['can_delete'] = canDelete;
                            this.mutable_providers[idx]['connection_count'] = connectionCount;
                            this.mutable_providers.sort( (a,b) => {
                                return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
                            });
                            this.success = response.msg;
                        } else {
                            this.success = '';
                            this.failure = response.msg;
                        }
                    });
                  }
                });
              } else {
                // Apply update
                this.form.patch('/global/providers/'+this.current_provider_id)
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Update the provider entry in the mutable array
                        this.mutable_providers[idx] = Object.assign({}, response.provider);
                        this.mutable_providers[idx]['can_delete'] = canDelete;
                        this.mutable_providers[idx]['connection_count'] = connectionCount;
                        this.mutable_providers.sort((a,b) => {
                            return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
              }
            // Create new global provider
            } else {
                this.form.post('/global/providers')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new provider onto the mutable array and re-sort it
                        this.mutable_providers.push(response.provider);
                        this.mutable_providers.sort((a,b) => {
                            return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            }
            this.dtKey += 1;           // force re-render of the datatable
            this.provDialog = false;
        },
        destroy (gpid) {
            let warning_html = "Deleting a platform cannot be reversed, only manually recreated."+
                               " Because this platform has no harvested usage data, it can be safely"+
                               " deleted.<br />";
            warning_html += "<strong>NOTE:</strong><br />ALL Platform entries defined across ALL instances will also";
            warning_html += " be removed if they exist - INCLUDING all related SUSHI credentials.";
            Swal.fire({
              title: 'Are you sure?', html: warning_html, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/global/providers/'+gpid)
                       .then( (response) => {
                           if (response.data.result) {
                               this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == gpid),1);
                               this.success = "Platform deleted successfully.";
                               this.dtKey += 1;
                           } else {
                               this.success = '';
                               this.failure = response.data.msg;
                           }
                       })
                       .catch({});
              }
            })
            .catch({});
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
        doExport () {
            window.location.assign('/global/providers/export/xlsx');
        },
        goURL (target) { window.open(target, "_blank"); },
    },
    computed: {
      ...mapGetters(['datatable_options'])
    },
    beforeCreate() {
        // Load existing store data
	    	this.$store.commit('initialiseStore');
  	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','globalproviders');
	  },
    mounted() {
      // Initialize the connection fields and reports checkboxes for a new provider
      this.all_connectors.forEach( cnx => {
        this.new_provider.connector_state[cnx['name']] = (cnx['id']==1) ? true : false;
      });
      this.master_reports.forEach( rpt => {
        this.new_provider.report_state[rpt['name']] = false;
      });
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      // Load providers
      this.updateRecords();
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('GlobalProviderData Component mounted.');
    }
  }
</script>
<style scoped>
.verydense { max-height: 16px; }
.centered-input >>> input {
  text-align: center
}
</style>
