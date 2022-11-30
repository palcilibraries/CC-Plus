<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col v-if="is_admin" class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="createForm">Create a Provider</v-btn>
        </v-col>
        <v-col v-if="is_admin" class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="providerImportForm">Import Providers</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="settingsImportForm">Import Sushi Settings</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="2">
          <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col v-if="is_admin" class="d-flex px-2" cols="3">&nbsp;</v-col>
        <v-col v-if="is_admin" class="d-flex px-2" cols="3">
          <a @click="doProvExport">
            <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Providers to Excel
          </a>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <a @click="doSushiExport">
            <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Sushi Settings to Excel
          </a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_providers" item-key="id" :options="mutable_options"
                    :search="search" @update:options="updateOptions" :key="dtKey">
        <template v-slot:item.inst_name="{ item }">
          <span v-if="item.inst_id==1">{{ item.institution.name }}</span>
          <span v-else><a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a></span>
        </template>
        <template v-slot:item.action="{ item }" v-if="is_admin || is_manager">
          <span class="dt_action">
            <v-icon title="Edit Provider" @click="goEdit(item.id)">mdi-cog-outline</v-icon>
            &nbsp; &nbsp;
            <v-icon title="Delete Provider" @click="destroy(item.id)">mdi-trash-can-outline</v-icon>
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
    <v-dialog v-model="settingsImportDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input><br />
            <p>
              <strong>Note:&nbsp; The Import Type below determines whether the settings in the input file should
              be treated as an <em>Update</em> or as a <em>Full Replacement</em> for any existing settings.</strong>
            </p>
            <p>
              When "Full Replacement" is chosen, any EXISTING SETTINGS omitted from the import file will be deleted!
              This will also remove all associated harvest and failed-harvest records connected to the settings!
            </p>
            <p>
              The "Add or Update" option will not delete any sushi settings, but will overwrite existing settings
              whenever a match for an Institution-ID and Provider-ID are found in the import file. If no setting
              exists for a given valid provider-institution pair, a new setting will be created and saved. Any values
              in columns C-G which are NULL, blank, or missing for a valid provider-institution pair, will result
              in a NULL value being stored for that field.
            </p>
            <p>
              For these reasons, exercise caution using this import function, especially when requesting a Full
              Replacement import. Generating an export of the existing settings FIRST will provide detailed
              instructions for importing on the "How to Import" tab and will help ensure that the desired
              end-state is achieved.
            </p>
            <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="settingsImportSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="settingsImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="provDialog" persistent max-width="500px">
      <v-card>
        <v-card-title>
          <span>Create a new provider</span>
        </v-card-title>
        <v-form v-model="formValid">
          <v-card-text>
            <v-container grid-list-md>
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
              <div v-if="is_admin">
                <v-select outlined required :items="institutions" v-model="form.inst_id" value="current_user.inst_id"
                          label="Institution" item-text="name" item-value="id"
                ></v-select>
              </div>
              <div v-else>
                <v-text-field outlined readonly label="Institution" :value="inst_name"></v-text-field>
              </div>
              <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
                <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line type="number"
                              :rules="dayRules">
                </v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Maximum #-of Retries'"></v-subheader>
                <v-text-field v-model="form.max_retries" label="Max Retries" hide-details single-line type="number"
                ></v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Reports to Harvest'"></v-subheader>
                <v-select :items="master_reports" v-model="form.master_reports" value="provider.reports" item-value="id"
                          item-text="name" label="Select" multiple chips hint="Choose which reports to harvest"
                          persistent-hint
                ></v-select>
              </div>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-col class="d-flex">
              <v-btn small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                Save New Provider
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
  import { mapGetters } from 'vuex';
  import Swal from 'sweetalert2';
  export default {
    props: {
            providers: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
            default_retries: { type:Number, default: null },
           },
    data () {
      return {
        success: '',
        failure: '',
        provDialog: false,
        providerImportDialog: false,
        settingsImportDialog: false,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement'],
        inst_name: '',
        headers: [
          { text: 'Provider ', value: 'name', align: 'start' },
          { text: 'Status', value: 'active' },
          { text: 'Serves', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month', align: 'center' },
        ],
        mutable_providers: this.providers,
        form: new window.Form({
            name: '',
            inst_id: 1,
            is_active: 1,
            server_url_r5: '',
            day_of_month: 15,
            max_retries: this.default_retries,
            master_reports: [],
        }),
        formValid: true,
        dayRules: [
            v => !!v || "Day of month is required",
            v => ( v && v >= 1 ) || "Day of month must be > 1",
            v => ( v && v <= 28 ) || "Day of month must be < 29",
        ],
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        search: '',
      }
    },
    methods:{
        providerImportForm () {
            this.csv_upload = null;
            this.providerImportDialog = true;
            this.settingsImportDialog = false;
            this.provDialog = false;
        },
        settingsImportForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.settingsImportDialog = true;
            this.providerImportDialog = false;
            this.provDialog = false;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.form.name = '';
            this.form.inst_id = (this.is_admin) ? 1 : this.institutions[0].id;
            this.form.is_active = 1;
            this.form.server_url_r5 = '';
            this.form.day_of_month = 15;
            this.form.master_reports = [];
            this.provDialog = true;
            this.providerImportDialog = false;
            this.settingsImportDialog = false;
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
        settingsImportSubmit (event) {
            this.success = '';
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            formData.append('type', this.import_type);
            axios.post('/sushisettings/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.settingsImportDialog = false;
        },
        doProvExport () {
            window.location.assign('/providers/export/xlsx');
        },
        doSushiExport () {
            window.location.assign('/sushi-export');
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.form.post('/providers')
                .then((response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new provider onto the mutable array and re-sort it
                        this.mutable_providers.push(response.provider);
                        this.mutable_providers.sort((a,b) => {
                          if ( a.prov_name < b.prov_name ) return -1;
                          if ( a.prov_name > b.prov_name ) return 1;
                          return 0;
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            this.provDialog = false;
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
        destroy (provid) {
            Swal.fire({
              title: 'Are you sure?',
              text: "Deleting a provider cannot be reversed, only manually recreated."+
                    " Because this provider has no harvested usage data, it can be safely"+
                    " deleted. NOTE: All SUSHI settings connected to this provider"+
                    " will also be removed.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/providers/'+provid)
                       .then( (response) => {
                           if (response.data.result) {
                               this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == provid),1);
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
        goEdit (provId) {
            window.location.assign('/providers/'+provId+'/edit');
        },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin','datatable_options'])
    },
    beforeCreate() {
        // Load existing store data
		this.$store.commit('initialiseStore');
	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','providers');
	},
    mounted() {
      if (!this.is_admin) {
          this.inst_name = this.institutions[0].name;
      }
      // Add empty header for admin/manager for the "actions" column in the datatable
      if (this.is_admin || this.is_manager) {
          this.headers.push({ text: '', value: 'action' });
      }

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('ProviderData Component mounted.');
    }
  }
</script>
<style>

</style>
