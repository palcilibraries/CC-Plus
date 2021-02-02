<template>
  <div>
    <div>
      <v-row>
        <v-col cols="2"><v-btn small color="primary" @click="importForm">Import Institutions</v-btn></v-col>
        <v-col><v-btn small color="primary" @click="createForm">Create an Institution</v-btn></v-col>
      </v-row>
      <v-row>
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/institutions/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/institutions/export/xlsx'">.xlsx</a>
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
            <td>{{ item.type }}</td>
            <td v-if="item.is_active">Active</td>
            <td v-else>Inactive</td>
            <td>{{ item.groups }}</td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <v-dialog v-model="importDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Institutions</v-card-title>
        <v-spacer></v-spacer>
        <v-card-subtitle><strong>Institutions cannot be deleted during an import operation.</strong>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-layout wrap>
              <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
              <p>
                <strong>NOTE:</strong> Import Type below refers to the row(s) of Sushi Settings which may, or may not,
                follow an institution record in the input CSV file. When "Full Replacement" is chosen, the existing
                settings for any provider not included in the import file will be deleted! This will also remove all
                associated harvest and failed-harvest records connected to the settings.
              </p>
              <p>
                Regardless of the Import Type, the first record in the import file for any institution (based on ID or
                name) will be used to update the institution's record (columns B through G). These values, including the
                group assignments in column-F, will replace whatever is currently defined for the given institution.
              </p>
              <p>
                For these reasons, use caution when using this import function, especially when requesting a Full
                Replacement import. Generating an institution export FIRST will provide detailed instructions for
                importing on the "How to Import" tab and help ensure that the desired end-state is achieved.
              </p>
              <p>
                The "Add or Update" option will not delete any sushi settings, but will overwrite existing settings
                whenever a match for an institution-ID and Provider-ID are found in the import file. If no setting for
                a given Institution-ID and Provider-ID currently exist, the setting will be added.
              </p>
              <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
            </v-layout>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
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
              <v-select :items="types" v-model="form.type_id" item-text="name" item-value="id"
                        label="Institution Type" outlined
              ></v-select>
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
            types: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        instDialog: false,
        importDialog: false,
        headers: [
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Type', value: 'type' },
          { text: 'Status', value: 'is_active' },
          { text: 'Group(s)', value: 'groups' },
        ],
        mutable_institutions: this.institutions,
        form: new window.Form({
            name: '',
            is_active: 1,
            fte: 0,
            type_id: 1,
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
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.instDialog = false;
            this.importDialog = true;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.form.name = '';
            this.form.is_active = 1;
            this.form.fte = 0;
            this.form.type_id = 1;
            this.form.institutiongroups = [];
            this.form.notes = '';
            this.instDialog = true;
            this.importDialog = false;
        },
        importSubmit (event) {
            this.success = '';
            if (this.import_type == '') {
                this.failure = 'An import type is required';
                return;
            }
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            formData.append('type', this.import_type);
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
             this.importDialog = false;
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
      ...mapGetters(['datatable_options'])
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
