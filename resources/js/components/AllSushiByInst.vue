<template>
  <div>
    <div v-if="is_manager">
      <v-row class="d-flex mb-4" no-gutters>
        <v-col class="d-flex pa-0" cols="3">
          <v-btn small color="primary" type="button" @click="importForm" class="section-action">
            Import Sushi Settings
          </v-btn>
        </v-col>
        <v-col class="d-flex px-1" cols="3">
          <a @click="doExport">Export to Excel</a>
          <!-- <a :href="'/sushisettings/export/xlsx/'+inst_id">Export to Excel</a> -->
        </v-col>
      </v-row>
    </div>
    <div v-if="(is_manager || is_admin) && mutable_unset.length > 0">
      <form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
            @keydown="form.errors.clear($event.target.name)">
        <input v-model="form.inst_id" id="inst_id" type="hidden">
        <v-col class="d-flex pa-0" cols="5">
          <v-select :items="mutable_unset" v-model="form.prov_id" @change="onUnsetChange" outlined
                    placeholder="Connect a Provider" item-text="name" item-value="id" color="primary"
          ></v-select>
        </v-col>
        <div v-if="showForm" class="form-fields">
          <template v-for="cnx in connectors">
            <v-text-field v-model="form[cnx.name]" :label='cnx.label' :id='cnx.name' outlined></v-text-field>
            &nbsp; &nbsp;
          </template>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Connect</v-btn>
          <v-btn small color="secondary" type="button" @click="testSettings">Test Settings</v-btn>
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
          <div v-if="showTest">
            <div>{{ testStatus }}</div>
            <div v-for="row in testData">{{ row }}</div>
          </div>
        </div>
	    </form>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-dialog v-model="importDialog" max-width="1200px">
        <v-card>
          <v-card-title>Import Sushi Settings</v-card-title>
          <v-card-text>
            <v-container grid-list-md>
              <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
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
      <v-data-table :headers="headers" :items="mutable_settings" item-key="id" class="elevation-1">
        <template v-slot:item="{ item }" >
          <tr>
            <td><a :href="'/providers/'+item.provider.id">{{ item.provider.name }}</a></td>
            <td v-if="all_connectors.some(c => (c.name == 'customer_id'))">{{ item.customer_id }}</td>
            <td v-if="all_connectors.some(c => (c.name == 'requestor_id'))">{{ item.requestor_id }}</td>
            <td v-if="all_connectors.some(c => (c.name == 'api_key'))">{{ item.api_key }}</td>
            <td v-if="all_connectors.some(c => (c.name == 'extra_args'))">{{ item.extra_args }}</td>
            <td :class="item.status">{{ item.status }}</td>
            <td v-if="is_manager || is_admin">
              <a :href="'/sushisettings/'+item.id+'/edit'">
                <v-icon title="Settings and harvests" :href="'/sushisettings/'+item.id+'/edit'">mdi-cog-outline</v-icon>
              </a>
              &nbsp; &nbsp;
              <v-icon title="Delete connection" @click="destroy(item)">mdi-trash-can-outline</v-icon>
            </td>
          </tr>
        </template>
        <tr v-if="is_manager || is_admin"><td colspan="6">&nbsp;</td></tr>
        <tr v-else><td colspan="4">&nbsp;</td></tr>
      </v-data-table>
    </div>
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
                settings: { type:Array, default: () => [] },
                unset: { type:Array, default: () => [] },
                inst_id: { type:Number, default: 0 },
                all_connectors: { type:Array, default: () => [] }
               },
        data() {
            return {
                success: '',
                failure: '',
                testData: '',
                testStatus: '',
				        showForm: false,
                showTest: false,
                importDialog: false,
                csv_upload: null,
                export_filters: { 'inst': [this.inst_id], 'prov': [] },
                mutable_settings: this.settings,
                mutable_unset: this.unset,
                connectors: [],
                // Actual headers are built from these in mounted()
                header_fields: [
                  { label: 'Name ', name: 'name' },
                  { label: '', name: 'customer_id' },
                  { label: '', name: 'requestor_id' },
                  { label: '', name: 'api_key' },
                  { label: '', name: 'extra_args' },
                  { label: 'Status', name: 'status' },
                  { label: '', name: ''},
                ],
                headers: [],
                form: new window.Form({
                    inst_id: this.inst_id,
                    prov_id: null,
                    customer_id: '',
                    requestor_id: '',
                    api_key: '',
                    extra_args: '',
                    status: 'Enabled'
				        })
            }
        },
        methods: {
          importForm () {
              this.csv_upload = null;
              this.importDialog = true;
          },
          doExport () {
              let url = "/sushi-export?filters="+JSON.stringify(this.export_filters);
              window.location.assign(url);
          },
	        formSubmit (event) {
                this.form.post('/sushisettings')
                    .then((response) => {
                        if (response.result) {
                            this.failure = '';
                            this.success = response.msg;
                            // Add the new connection to the settings rows and sort it by-name ascending
                            this.mutable_settings.push(response.setting);
                            this.mutable_settings.sort((a,b) => {
                              if ( a.provider.name < b.provider.name ) return -1;
                              if ( a.provider.name > b.provider.name ) return 1;
                              return 0;
                            });
                            // Remove the unset row that just got added
                            let newid = response.setting.prov_id;
                            this.mutable_unset.splice(this.mutable_unset.findIndex(u=> u.id == newid),1);
                            this.form.inst_id = this.inst_id;
                            this.form.prov_id = '0';
                            this.form.customer_id = '';
                            this.form.requestor_id = '';
                            this.form.api_key = '';
                            this.form.extra_args = '';
                            this.showForm = false;
                        } else {
                            this.success = '';
                            this.failure = response.msg;
                        }
                    });
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
                             this.mutable_settings = response.data.settings;
                             this.success = response.data.msg;
                         } else {
                             this.failure = response.data.msg;
                         }
                     });
                this.importDialog = false;
            },
            destroy (setting) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting these settings cannot be reversed, only manually recreated.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/sushisettings/'+setting.id)
                           .then( (response) => {
                               if (response.data.result) {
                                   self.failure = '';
                                   self.success = response.data.msg;
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
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
            testSettings (event) {
                if (!(this.is_admin || this.is_manager)) { return; }
                this.failure = '';
                this.success = '';
                this.testData = '';
                this.testStatus = "... Working ...";
                this.showTest = true;
                var testArgs = {'prov_id' : this.form.prov_id};
                if (this.connectors.some(c => c.name === 'requestor_id')) testArgs['requestor_id'] = this.form.requestor_id;
                if (this.connectors.some(c => c.name === 'customer_id')) testArgs['customer_id'] = this.form.customer_id;
                if (this.connectors.some(c => c.name === 'api_key')) testArgs['api_key'] = this.form.api_key;
                if (this.connectors.some(c => c.name === 'extra_args')) testArgs['extra_args'] = this.form.extra_args;
                axios.post('/sushisettings-test', testArgs)
                     .then((response) => {
                        if (response.data.result == '') {
                            this.testStatus = "No results!";
                        } else {
                            this.testStatus = response.data.result;
                            this.testData = response.data.rows;
                        }
                    })
                   .catch(error => {});
            },
            onUnsetChange (prov) {
                this.form.customer_id = '';
                this.form.requestor_id = '';
                this.form.api_key = '';
                this.form.extra_args = '';
                this.failure = '';
                this.success = '';
                this.testData = '';
                this.testStatus = '';
                this.showForm = true;
                let provider = this.unset.find(p => p.id == prov);
                this.connectors = provider.connectors;
            },
            hideForm (event) {
                this.showForm = false;
                this.form.prov_id = null;
                this.connectors = [];
            },
        },
        computed: {
          ...mapGetters(['is_admin','is_manager']),
        },
        mounted() {
            // Sort the settings by provider name
            this.mutable_settings.sort((a,b) => {
                if ( a.provider.name < b.provider.name ) return -1;
                if ( a.provider.name > b.provider.name ) return 1;
                return 0;
            });
            // Setup DataTable headers array based on the provider connectors
            this.header_fields.forEach((fld) => {
                // Connection fields are setup in "header_fields" as names without labels
                if (fld.label == '' && fld.name != '') {
                    // any provider using the field means we make a column for it
                    let cnx = this.all_connectors.find(c => c.name == fld.name);
                    if (typeof(cnx) != 'undefined') {
                        this.headers.push({ text: cnx.label, value: cnx.name});
                    }
                } else {
                    this.headers.push({ text: fld.label, value: fld.name });
                }
            });
            console.log('Providers-by-Inst Component mounted.');
        }
    }
</script>

<style>
.Enabled { color: #00dd00; }
.Disabled { color: #dd0000; }
</style>
