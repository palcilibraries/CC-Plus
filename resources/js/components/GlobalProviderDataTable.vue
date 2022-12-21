<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="createForm()">Add a Global Provider</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="enableImportForm">Import Providers</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">&nbsp;</v-col>
        <v-col class="d-flex px-2" cols="3">
          <a :href="'/providers/export/xlsx'">Export providers to Excel</a>
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
    <v-dialog v-model="provDialog" persistent max-width="640px">
      <v-card>
        <v-card-title>
          <span>{{ dialog_title }}</span>
        </v-card-title>
        <v-form v-model="formValid">
          <v-card-text>
            <v-container grid-list-md>
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
              <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
                <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line type="number"
                              :rules="dayRules">
                </v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Connection Fields'"></v-subheader>
                  <v-select :items="all_connectors" v-model="form.connectors" item-value="id"
                            item-text="label" label="Select" multiple chips hint="Required connection fields"
                            persistent-hint
                  ></v-select>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Maximum #-of Retries'"></v-subheader>
                <v-text-field v-model="form.max_retries" label="Max Retries" hide-details single-line type="number"
                ></v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Reports to Harvest'"></v-subheader>
                <v-select :items="master_reports" v-model="form.master_reports" item-value="id"
                          item-text="name" label="Select" multiple chips hint="Define which reports can be harvested"
                          persistent-hint
                ></v-select>
              </div>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-col class="d-flex">
              <v-btn small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                Save Provider
              </v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="provDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
      </v-form>
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
            default_retries: { type:Number, default: null }
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
          { text: 'Status', value: 'status' },
          { text: 'Harvest Day', value: 'day_of_month', align: 'center' },
          { text: '', value: 'action', sortable: false },
        ],
        mutable_providers: [ ...this.providers],
        new_provider: {'id': null, 'name': '', 'is_active': 1, 'master_reports': [], 'server_url_r5': '',
                      'day_of_month': 15, 'max_retries': this.default_retries},
        formValid: true,
        form: new window.Form({
            name: '',
            is_active: 1,
            server_url_r5: '',
            day_of_month: 15,
            max_retries: 10,
            connectors: [],
            master_reports: [],
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
            this.form.day_of_month = _prov.day_of_month;
            this.form.max_retries = _prov.max_retries;
            this.form.connectors = _prov.connectors;
            this.form.master_reports = _prov.master_reports;
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
            this.form.day_of_month = this.new_provider.day_of_month;
            this.form.max_retries = this.new_provider.max_retries;
            this.form.connectors = this.new_provider.connectors;
            this.form.master_reports = this.new_provider.master_reports;
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
            axios.post('/providers/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response institutions
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
              this.form.patch('/globalproviders/'+this.current_provider_id)
              .then( (response) => {
                  if (response.result) {
                      this.failure = '';
                      this.success = response.msg;
                      // Update the provider entry in the mutable array
                      let idx = this.mutable_providers.findIndex(p => p.id == this.current_provider_id);
                      this.mutable_providers[idx] = response.provider;
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
                this.form.post('/globalproviders')
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
                  axios.delete('/globalproviders/'+gpid)
                       .then( (response) => {
                           if (response.data.result) {
                               this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == gpid),1);
                               this.success = "Selected institutions deleted successfully.";
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
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('GlobalProviderData Component mounted.');
    }
  }
</script>
<style>

</style>
