<template>
  <div>
    <div>
      <v-row v-if="is_admin" class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="createForm">Create an Institution</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="institutionImportForm">Import Institutions</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="settingsImportForm">Import Sushi Settings</v-btn>
        </v-col>
      </v-row>
      <v-row v-if="is_admin" class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">&nbsp;</v-col>
        <v-col class="d-flex px-2" cols="3">
            Export institutions to: &nbsp;
            <a :href="'/institutions/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/institutions/export/xlsx'">.xlsx</a>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          Export sushi settings to: &nbsp;
          <a :href="'/sushisettings/export/xls'">.xls</a> &nbsp; &nbsp;
          <a :href="'/sushisettings/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_institutions" item-key="id" :options="mutable_options"
                     :key="dtKey" @update:options="updateOptions">
        <template v-slot:item="{ item }">
          <tr>
            <td><a :href="'/institutions/'+item.id">{{ item.name }}</a></td>
            <td v-if="item.is_active">Active</td>
            <td v-else>Inactive</td>
            <td>{{ item.groups }}</td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <v-dialog v-model="institutionImportDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Institutions</v-card-title>
        <v-spacer></v-spacer>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Institution imports function exclusively as Updates. No existing institution
              records will be deleted.</strong>
            </p>
            <p>
              The import process overwrites existing settings whenever a match for a Institution-ID is found in column-A
              of the import file. If no existing setting is found for the specified Institution-ID, a NEW institution
              will be created with the fields specified. Institution names (column-B) must be unique. Attempting to
              create an institution (or rename one) using an existing name will be ignored.
            </p>
            <p>
              Institutions can be renamed via import by giving the ID in column-A and the replacement name in column-B.
              Be aware that the new name takes effect immediately, and will be associated with all harvested usage
              data that may have been collected using the OLD name (data is stored by the ID, not the name.)
            </p>
            <p>
              For these reasons, use caution when using this import function. Generating an Institution export FIRST
              will supply detailed instructions for importing on the "How to Import" tab. Generating a new Institution
              export AFTER an import operation is a good way to confirm that all the settings are as-desired.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="institutionImportSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="institutionImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="settingsImportDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-spacer></v-spacer>
        <v-card-text>
          <v-container grid-list-md>
            <v-layout wrap>
              <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
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
            </v-layout>
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
    <v-dialog v-model="instDialog" persistent max-width="500px">
      <v-card>
        <v-card-title>
          <span>Create a new institution</span>
        </v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
              <div class="field-wrapper">
                <v-subheader v-text="'FTE'"></v-subheader>
                <v-text-field v-model="form.fte" label="FTE" hide-details single-line type="number"></v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Belongs To'"></v-subheader>
                <v-select :items="all_groups" v-model="form.institutiongroups" item-text="name" item-value="id"
                          label="Institution Group(s)" multiple chips persistent-hint
                          hint="Assign group membership for this institution"
                ></v-select>
              </div>
              <v-textarea v-model="form.notes" label="Notes" auto-grow></v-textarea>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn class='btn' x-small color="primary" type="submit">Save New Institution</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="instDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        instDialog: false,
        institutionImportDialog: false,
        settingsImportDialog: false,
        headers: [
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Status', value: 'is_active' },
          { text: 'Group(s)', value: 'groups' },
        ],
        mutable_institutions: this.institutions,
        form: new window.Form({
            name: '',
            is_active: 1,
            fte: 0,
            institutiongroups: [],
            notes: '',
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement']
      }
    },
    methods: {
        institutionImportForm () {
            this.csv_upload = null;
            this.instDialog = false;
            this.institutionImportDialog = true;
            this.settingsImportDialog = false;
        },
        settingsImportForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.settingsImportDialog = true;
            this.institutionImportDialog = false;
            this.provDialog = false;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.form.name = '';
            this.form.is_active = 1;
            this.form.fte = 0;
            this.form.institutiongroups = [];
            this.form.notes = '';
            this.instDialog = true;
            this.settingsImportDialog = false;
            this.institutionImportDialog = false;
        },
        institutionImportSubmit (event) {
            this.success = '';
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/institutions/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response institutions
                         this.mutable_institutions = response.data.inst_data;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.institutionImportDialog = false;
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
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.form.post('/institutions')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new institution onto the mutable array and re-sort it
                        this.mutable_institutions.push(response.institution);
                        this.mutable_institutions.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            this.instDialog = false;
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
      ...mapGetters(['is_admin','datatable_options'])
    },
    beforeCreate() {
        // Load existing store data
		this.$store.commit('initialiseStore');
	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','institutions');
	},
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('InstitutionData Component mounted.');
    }
  }
</script>
