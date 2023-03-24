<template>
  <div>
    <div>
      <v-row class="d-flex mb-1 align-end">
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="createForm()">Add a Global Provider</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="enableImportForm">Import Providers</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <a @click="doExport">
            <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export providers to Excel
          </a>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
          ></v-text-field>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_providers" item-key="prov_id" :options="mutable_options"
                    :search="search" @update:options="updateOptions" :key="dtKey">
        <template v-slot:item.action="{ item }">
          <span class="dt_action">
            <v-btn icon @click="editForm(item.id)">
              <v-icon title="Edit Provider">mdi-cog-outline</v-icon>
            </v-btn>
            <v-btn v-if="item.can_delete" icon class="pl-4" @click="destroy(item.id)">
              <v-icon title="Delete Provider">mdi-trash-can-outline</v-icon>
            </v-btn>
            <v-btn v-else icon class="pl-4">
              <v-icon color="#c9c9c9">mdi-trash-can-outline</v-icon>
            </v-btn>
          </span>
        </template>
        <v-alert slot="no-results" :value="true" color="error" icon="warning">
          Your search for "{{ search }}" found no results.
        </v-alert>
      </v-data-table>
    </div>
    <v-dialog v-model="providerImportDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Providers</v-card-title>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ CSV Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Provider imports function exclusively as Updates. No existing provider records will
              be deleted.</strong>
            </p>
            <p>
              The import process overwrites existing settings whenever a match for a Provider-ID is found in column-A
              of the import file. If no existing setting is found for the specified Provider-ID, a NEW provider will
              be created with the fields specified. Provider names (column-B) must be unique. Attempting to create
              a provider (or rename one) using an existing name will be ignored.
            </p>
            <p>
              Providers can be renamed via import by giving the ID in column-A and the replacement name in column-B.
              Be aware that the new name takes effect immediately, and will be associated with all harvested usage
              data that may have been collected using the OLD name (data is stored by the ID, not the name.)
            </p>
            <p>
              For these reasons, use caution when using this import function. Generating a Provider export FIRST will
              supply detailed instructions for importing on the "How to Import" tab. Generating a new Provider export
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
    <v-dialog v-model="provDialog" persistent max-width="500px">
      <v-card>
        <v-container grid-list-sm>
          <v-form v-model="formValid">
            <v-row class="d-flex ma-2">
              <v-col class="d-flex pt-4 justify-center"><h4 align="center">{{ dialog_title }}</h4></v-col>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.name" label="Name" outlined dense></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined dense></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-col class="d-flex pr-2" cols="6">
                <v-list dense>
                  <v-list-item class="verydense"><strong>Connection Fields</strong></v-list-item>
                  <v-list-item v-for="cnx in all_connectors" :key="cnx.name" class="verydense">
                    <v-checkbox :value="form.connector_state[cnx.name]" :key="cnx.name" :label="cnx.label"
                                v-model="form.connector_state[cnx.name]" dense>
                    </v-checkbox>
                  </v-list-item>
                </v-list>
              </v-col>
              <v-col class="d-flex pl-2" cols="6">
                <v-list dense>
                  <v-list-item class="verydense"><strong>Available Reports</strong></v-list-item>
                  <v-list-item v-for="rpt in master_reports" :key="rpt.name" class="verydense">
                    <v-checkbox :value="form.report_state[rpt.name]" :key="rpt.name" :label="rpt.name"
                                v-model="form.report_state[rpt.name]" dense>
                    </v-checkbox>
                  </v-list-item>
                </v-list>
              </v-col>
            </v-row>
            <v-row v-if="form.connector_state.extra_args" class="d-flex ma-0">
              <v-col class="d-flex px-2" cols="12">
                <v-text-field v-model="form.extra_pattern" label="Extra Arguments Pattern" outlined dense></v-text-field>
              </v-col>
            </v-row>
            <v-row class="d-flex mx-2 mb-2 align-center">
              <v-col class="d-flex px-2" cols="4">
                <v-switch v-model="form.is_active" label="Active?" dense></v-switch>
              </v-col>
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                  Save Provider
                </v-btn>
              </v-col>
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" type="button" @click="provDialog=false">Cancel</v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-container>
      </v-card>
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
           },
    data () {
      return {
        success: '',
        failure: '',
        providerImportDialog: false,
        settingsImportDialog: false,
        provDialog: false,
        dialog_title: '',
        current_provider_id: null,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement'],
        search: '',
        headers: [
          { text: 'Provider ', value: 'name', align: 'start' },
          { text: 'Master Reports', value: 'reports_string' },
          { text: 'Sushi Connections', value: 'connection_count', align: 'center' },
          { text: 'Status', value: 'status' },
          { text: '', value: 'action', sortable: false },
        ],
        mutable_providers: [ ...this.providers],
        new_provider: {'id': null, 'name': '', 'is_active': 1, 'report_state': {}, 'connector_state': {}, 'server_url_r5': '',
                       'extra_pattern': null},
        formValid: true,
        form: new window.Form({
            name: '',
            is_active: 1,
            server_url_r5: '',
            connector_state: [],
            report_state: [],
            extra_pattern: null,
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
            this.dialog_title = "Edit Global Provider";
            let _prov = this.mutable_providers.find(p => p.id == gp_id);
            this.current_provider_id = gp_id;
            this.form.name = _prov.name;
            this.form.is_active = _prov.is_active;
            this.form.server_url_r5 = _prov.server_url_r5;
            this.form.connector_state = _prov.connector_state;
            this.form.report_state = _prov.report_state;
            this.form.extra_pattern = _prov.extra_pattern;
            this.providerImportDialog = false;
            this.settingsImportDialog = false;
            this.provDialog = true;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.dialog_title = "Add New Global Provider";
            this.form.name = this.new_provider.name;
            this.form.is_active = this.new_provider.is_active;
            this.form.server_url_r5 = this.new_provider.server_url_r5;
            this.form.connector_state = this.new_provider.connector_state;
            this.form.report_state = this.new_provider.report_state;
            this.form.extra_pattern = this.new_provider.extra_pattern;
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
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            // Update existing global provider
            if (this.dialog_title == "Edit Global Provider") {
              let idx = this.mutable_providers.findIndex(p => p.id == this.current_provider_id);
              var canDelete = this.mutable_providers[idx].can_delete;
              var connectionCount = this.mutable_providers[idx].connection_count;
              this.form.patch('/global/providers/'+this.current_provider_id)
              .then( (response) => {
                  if (response.result) {
                      this.failure = '';
                      this.success = response.msg;
                      // Update the provider entry in the mutable array
                      this.mutable_providers[idx] = response.provider;
                      this.mutable_providers[idx]['can_delete'] = canDelete;
                      this.mutable_providers[idx]['connection_count'] = connectionCount;
                      this.mutable_providers.sort((a,b) => {
                        if ( a.name < b.name ) return -1;
                        if ( a.name > b.name ) return 1;
                        return 0;
                      });
                  } else {
                      this.success = '';
                      this.failure = response.msg;
                  }
              });

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
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
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
            let warning_html = "Deleting a provider cannot be reversed, only manually recreated."+
                               " Because this provider has no harvested usage data, it can be safely"+
                               " deleted.<br />";
            warning_html += "<strong>NOTE:</strong><br />ALL Provider entries defined across ALL instances will also";
            warning_html += " be removed if they exist - INCLUDING all related sushi settings.";
            Swal.fire({
              title: 'Are you sure?', html: warning_html, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/global/providers/'+gpid)
                       .then( (response) => {
                           if (response.data.result) {
                               this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == gpid),1);
                               this.success = "Global provider deleted successfully.";
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
