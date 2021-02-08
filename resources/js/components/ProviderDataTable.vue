<template>
  <div>
    <div>
      <v-row v-if="is_admin" class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="importForm">Import Providers</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="createForm">Create a Provider</v-btn>
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/providers/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/providers/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_providers" item-key="prov_id" :options="mutable_options"
                     :key="dtKey" @update:options="updateOptions">
        <template v-slot:item="{ item }">
          <tr>
            <td v-if="is_admin || is_manager">
              <a :href="'/providers/'+item.prov_id">{{ item.prov_name }}</a>
            </td>
            <td v-else>{{ item.prov_name }}</a>
            <td v-if="item.is_active">Active</td>
            <td v-else>Inactive</td>
            <td v-if="item.inst_id==1">Entire Consortium</td>
            <td v-else><a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a></td>
            <td>{{ item.day_of_month }}</td>
            <td>&nbsp;</td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <v-dialog v-model="importDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Providers</v-card-title>
        <v-spacer></v-spacer>
        <v-card-subtitle><strong>Providers cannot be deleted during an import operation.</strong>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-layout wrap>
              <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
              <p>
                <strong>NOTE: </strong>Provider imports always function as full-replacement updates. Any settings for
                columns C-G which are NULL, blank, or missing in the import file will cause the import to overwrite the
                field(s) for the provider with the blank, empty, or NULL value.
              </p>
              <p>
                Provider-IDs (column-A) should be sequential, but this is not required. Importing rows with new, unique
                ID values will create new providers. Provider names (column-B) must be unique. Attempting to renamed a
                provider to an existing name will be ignored.
              </p>
              <p>
                Providers can be renamed via import by giving the ID in column-A and the replacement name in column-B.
                Be aware that the new name takes effect immediately, and will be associated with all harvested usage
                data that may have been collected using the OLD name (data is stored by the ID, not the name.)
              </p>
              <p>
                For these reasons, use caution when using this import function. Generating a Provider export FIRST will
                supply detailed instructions for importing on the "How to Import" tab. Generating a new Provider export
                following an import operation is a good way to confirm that all the settings are as-desired.
              </p>
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
    <v-dialog v-model="provDialog" persistent max-width="500px">
      <v-card>
        <v-card-title>
          <span>Create a new provider</span>
        </v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
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
                <v-text-field v-model="form.day_of_month" label="Day-of-Month" hide-details single-line type="number"
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
              <v-btn class='btn' x-small color="primary" type="submit">Save New Provider</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="provDialog=false">Cancel</v-btn>
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
            providers: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        provDialog: false,
        importDialog: false,
        inst_name: '',
        headers: [
          { text: 'Provider ', value: 'prov_name', align: 'start' },
          { text: 'Status', value: 'is_active' },
          { text: 'Serves', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month' },
          { text: 'Action', value: '' },
        ],
        mutable_providers: this.providers,
        form: new window.Form({
            name: '',
            inst_id: 1,
            is_active: 1,
            server_url_r5: '',
            day_of_month: 15,
            master_reports: [],
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
      }
    },
    methods:{
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.importDialog = true;
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
            this.importDialog = false;
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
             this.importDialog = false;
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
