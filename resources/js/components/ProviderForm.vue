<template>
  <div>
    <div class="page-header"><h1>{{ form.name }}</h1></div>
    <div class="details" :key="'details_'+dtKey">
      <v-row v-if="can_edit && !showForm" no-gutters>
        <v-col class="d-flex pa-0">
          <h3 class="section-title">Details &nbsp; &nbsp;</h3>
          <v-icon v-if="!showForm" title="Edit Provider Settings" @click="showForm=true">mdi-cog-outline</v-icon>
          &nbsp;
          <v-icon v-if="is_admin && mutable_prov.can_delete" title="Delete Provider" @click="destroy(mutable_prov.id)">
            mdi-trash-can-outline
          </v-icon>
        </v-col>
  	    <div class="status-message" v-if="success || failure">
  	      <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </div>
      </v-row>
      <!-- form display control and confirmations  -->
      <!-- Values-only when form not active -->
      <div v-if="!showForm">
  	    <v-simple-table dense>
          <tr><td>Name</td><td>{{ mutable_prov.name }}</td></tr>
          <tr><td>Status</td><td>{{ status }}</td></tr>
  	      <tr><td>Serves</td><td>{{ inst_name }}</td></tr>
  	      <tr><td>SUSHI service URL</td><td>{{ mutable_prov.server_url_r5 }}</td></tr>
          <tr>
  	        <td>Connection Fields</td>
  	        <td>
  	          <template v-for="cnx in mutable_prov.connectors">
                <v-chip>{{ cnx.name }}</v-chip>
              </template>
  	        </td>
  	      </tr>
  	      <tr><td>Run harvests monthly on day</td><td>{{ mutable_prov.day_of_month }}</td></tr>
          <tr><td>Maximum retries</td><td>{{ mutable_prov.max_retries }}</td></tr>
  	      <tr>
  	        <td>Reports to harvest </td>
  	        <td>
  	          <template v-for="report in master_reports">
                <v-chip v-if="mutable_prov.reports.some(r => r.id === report.id)">
  	              {{ report.name }}
  	            </v-chip>
  	          </template>
  	        </td>
  	      </tr>
  	    </v-simple-table>
      </div>
      <div v-else>
        <v-form v-model="formValid" class="in-page-form">
          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          <v-switch v-model="form.is_active" label="Active?"></v-switch>
          <v-select :items="institutions" v-model="form.inst_id" value="provider.inst_id" label="Serves"
                    item-text="name" item-value="id" outlined
          ></v-select>
          <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
          <div v-if="is_admin" class="field-wrapper has-label">
            <v-subheader v-text="'Required Connection Fields'"></v-subheader>
            <v-select :items="all_fields" v-model="form.connectors" value="provider.connectors" label="Select"
                      item-text="name" item-value="id" multiple chips
            ></v-select>
          </div>
  		    <div class="field-wrapper has-label">
  	        <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
  	        <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line type="number"
	                      :rules="dayRules"></v-text-field>
          </div>
          <div class="field-wrapper has-label">
            <v-subheader v-text="'Maximum #-of Retries'"></v-subheader>
            <v-text-field v-model="form.max_retries" label="Max Retries" hide-details single-line type="number"
            ></v-text-field>
          </div>
          <div class="field-wrapper has-label">
	          <v-subheader v-text="'Reports to Harvest'"></v-subheader>
	          <v-select :items="master_reports" v-model="form.master_reports" value="provider.reports" label="Select"
	                    item-text="name" item-value="id" multiple chips hint="Choose which reports to harvest"
                      persistent-hint
	          ></v-select>
		      </div>
          <br />
          <v-btn small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
              Save Provider Settings
          </v-btn>
          &nbsp; &nbsp;
          <v-btn small type="button" @click="showForm=false">cancel</v-btn>
        </v-form>
      </div>
    </div>
    <div class="related-list">
      <v-expansion-panels><v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Recent Harvest Activity</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <div v-if="harvests.length > 0">
            <harvestlog-summary-table :harvests='harvests' :prov_id="provider.id"></harvestlog-summary-table>
          </div>
          <div v-else>
            <p>No harvest records found for this provider</p>
          </div>
  	    </v-expansion-panel-content>
	    </v-expansion-panel></v-expansion-panels>
    </div>
    <div class="related-list">
      <h3 class="section-title">Sushi Settings by Institution </h3>
      <div v-if="is_manager">
        <v-row class="d-flex mb-4" no-gutters>
          <v-col class="d-flex pa-0" cols="3">
            <v-btn small color="primary" type="button" @click="enableImportDialog" class="section-action">
              Import Sushi Settings
            </v-btn>
          </v-col>
          <v-col class="d-flex px-1" cols="3">
            <a @click="doExport"><v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export to Excel</a>
          </v-col>
        </v-row>
      </div>
      <div v-if="(is_manager || is_admin) && mutable_unset.length > 0">
        <form method="POST" action="/sushisettings" @submit.prevent="sushiFormSubmit"
              @keydown="sushiForm.errors.clear($event.target.name)">
          <input v-model="sushiForm.prov_id" id="provider.id" type="hidden">
          <v-col class="d-flex pa-0" cols="5">
            <v-select :items="mutable_unset" v-model="sushiForm.inst_id" @change="onUnsetChange"
                      placeholder="Connect an Institution" item-text="name" item-value="id" outlined
            ></v-select>
          </v-col>
          <div v-if="showSushiForm" class="form-fields">
            <template v-for="cnx in mutable_prov.connectors">
              <v-text-field v-model="sushiForm[cnx.name]" :label='cnx.label' :id='cnx.name' outlined></v-text-field>
              &nbsp; &nbsp;
            </template>
            <v-btn small color="primary" type="submit" :disabled="sushiForm.errors.any()">Connect</v-btn>
            <v-btn small color="secondary" type="button" @click="testSettings">Test Settings</v-btn>
            <v-btn small type="button" @click="hideSushiForm">cancel</v-btn>
            <div v-if="showTest">
              <div>{{ testStatus }}</div>
              <div v-for="row in testData">{{ row }}</div>
            </div>
          </div>
    	  </form>
      </div>
      <v-data-table :headers="headers" :items="mutable_prov.sushiSettings" item-key="id" :key="'setdt_'+dtKey">
        <template v-slot:item="{ item }" >
          <tr>
            <td><a :href="'/institutions/'+item.institution.id">{{ item.institution.name }}</a></td>
            <td v-if="mutable_prov.connectors.some(c => c.name === 'customer_id')">{{ item.customer_id }}</td>
            <td v-if="mutable_prov.connectors.some(c => c.name === 'requestor_id')">{{ item.requestor_id }}</td>
            <td v-if="mutable_prov.connectors.some(c => c.name === 'API_key')">{{ item.API_key }}</td>
            <td v-if="mutable_prov.connectors.some(c => c.name === 'extra_args')">{{ item.extra_args }}</td>
            <td :class="item.status">{{ item.status }}</td>
            <td v-if="is_manager || is_admin">
              <a :href="'/sushisettings/'+item.id+'/edit'">
                <v-icon title="Settings and harvests" :href="'/sushisettings/'+item.id+'/edit'">mdi-cog-outline</v-icon>
              </a>
              &nbsp; &nbsp;
              <v-icon title="Delete connection" @click="destroySushi(item)">mdi-trash-can-outline</v-icon>
            </td>
          </tr>
        </template>
        <tr v-if="is_manager || is_admin"><td colspan="6">&nbsp;</td></tr>
        <tr v-else><td colspan="4">&nbsp;</td></tr>
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
    </div>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                provider: { type:Object, default: () => {} },
                institutions: { type:Array, default: () => [] },
                unset: { type:Array, default: () => [] },
                master_reports: { type:Array, default: () => [] },
                all_fields: { type:Array, default: () => [] },
                harvests: { type:Array, default: () => [] },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
                inst_name: '',
                testData: '',
                testStatus: '',
                can_edit: false,
                showForm: false,
                showTest: false,
                showSushiForm: false,
                importDialog: false,
                csv_upload: null,
                export_filters: { 'inst': [], 'prov': [this.provider.id] },
                mutable_prov: { ...this.provider },
                mutable_unset: [ ...this.unset ],
                dtKey: 1,
                // Actual headers are built from these in mounted()
                headers: [],
                header_fields: [
                  { label: 'Name ', name: 'name' },
                  { label: '', name: 'customer_id' },
                  { label: '', name: 'requestor_id' },
                  { label: '', name: 'API_key' },
                  { label: '', name: 'extra_args' },
                  { label: 'Status', name: 'status' },
				          { label: '', name: ''}
                ],
                form: new window.Form({
                    name: this.provider.name,
                    inst_id: this.provider.inst_id,
                    is_active: this.provider.is_active,
                    server_url_r5: this.provider.server_url_r5,
                    day_of_month: this.provider.day_of_month,
                    max_retries: this.provider.max_retries,
                    connectors: [],
                    master_reports: [],
                }),
                sushiForm: new window.Form({
                    inst_id: null,
                    prov_id: this.provider.id,
                    customer_id: '',
                    requestor_id: '',
                    API_key: '',
                    extra_args: '',
                    status: 'Enabled'
                }),
                formValid: true,
                dayRules: [
                    v => !!v || "Day of month is required",
                    v => ( v && v >= 1 ) || "Day of month must be > 1",
                    v => ( v && v <= 28 ) || "Day of month must be < 29",
                ],
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                this.form.patch('/providers/'+this.provider['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.mutable_prov = response.provider;
                            this.form.max_retries = response.provider.max_retries;
                            if (this.is_admin) {
                                this.inst_name = this.institutions[response.provider.inst_id-1].name;
                            }
                            this.status = this.statusvals[response.provider.is_active];
                            this.success = response.msg;
                            this.dtKey += 1;  // settings changed, refresh datatable
                        } else {
                            this.failure = response.msg;
                        }
                });
                this.showForm = false;
            },
            hideSushiForm () {
                this.showSushiForm = false;
                this.sushiForm.inst_id = null;
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
                                   window.location.assign("/providers");
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
            destroySushi (setting) {
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
                       this.mutable_unset.push({'id': setting.inst_id, 'name': setting.institution.name});
                       this.mutable_unset.sort((a,b) => {
                         if ( a.name < b.name ) return -1;
                         if ( a.name > b.name ) return 1;
                         return 0;
                       });
                       // Remove the setting from the "set" list
                       this.mutable_prov.sushiSettings.splice(this.mutable_prov.sushiSettings.findIndex(s=> s.id == setting.id),1);
                       this.dtKey += 1;           // re-render of the datatable
                    }
                  })
                  .catch({});
              },
            enableImportDialog () {
                this.csv_upload = null;
                this.importDialog = true;
            },
            doExport () {
                let url = "/sushi-export?filters="+JSON.stringify(this.export_filters);
                window.location.assign(url);
            },
            sushiFormSubmit (event) {
                this.sushiForm.post('/sushisettings')
	                .then((response) => {
                      if (response.result) {
                          this.failure = '';
                          this.success = response.msg;
                          // Add the new connection to the settings rows and sort it by-name ascending
                          this.mutable_prov.sushiSettings.push(response.setting);
                          this.mutable_prov.sushiSettings.sort((a,b) => {
                            if ( a.institution.name < b.institution.name ) return -1;
                            if ( a.institution.name > b.institution.name ) return 1;
                            return 0;
                          });
                          // Remove the unset row that just got added
                          let newid = response.setting.inst_id;
                          this.mutable_unset.splice(this.mutable_unset.findIndex(s=> s.id == newid),1);
                          this.sushiForm.inst_id = '0';
                          this.sushiForm.prov_id = this.prov_id;
                          this.sushiForm.customer_id = '';
                          this.sushiForm.requestor_id = '';
                          this.sushiForm.API_key = '';
                          this.sushiForm.extra_args = '';
                          this.showSushiForm = false;
                          this.dtKey += 1;
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
                  formData.append('prov_id', this.prov_id);
                  axios.post('/sushisettings/import', formData, {
                          headers: { 'Content-Type': 'multipart/form-data' }
                        })
                       .then( (response) => {
                           if (response.data.result) {
                               this.mutable_prov.sushiSettings = response.data.settings;
                               this.success = response.data.msg;
                           } else {
                               this.failure = response.data.msg;
                           }
                       });
                  this.dtKey += 1;
                  this.importDialog = false;
              },
              testSettings (event) {
                  if (!(this.is_admin || this.is_manager)) { return; }
                  this.failure = '';
                  this.success = '';
                  this.testData = '';
                  this.testStatus = "... Working ...";
                  this.showTest = true;
                  var testArgs = {'prov_id' : this.sushiForm.prov_id};
                  if (this.mutable_prov.connectors.some(c => c.name === 'requestor_id'))
                      testArgs['requestor_id'] = this.sushiForm.requestor_id;
                  if (this.mutable_prov.connectors.some(c => c.name === 'customer_id'))
                      testArgs['customer_id'] = this.sushiForm.customer_id;
                  if (this.mutable_prov.connectors.some(c => c.name === 'API_key'))
                      testArgs['API_key'] = this.sushiForm.API_key;
                  if (this.mutable_prov.connectors.some(c => c.name === 'extra_args'))
                      testArgs['extra_args'] = this.sushiForm.extra_args;
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
              onUnsetChange () {
                  this.sushiForm.customer_id = '';
                  this.sushiForm.requestor_id = '';
                  this.sushiForm.API_key = '';
                  this.sushiForm.extra_args = '';
                  this.failure = '';
                  this.success = '';
                  this.testData = '';
                  this.testStatus = '';
                  this.showSushiForm = true;
              },
        },
        computed: {
          ...mapGetters(['is_manager','is_admin','user_inst_id'])
        },
        mounted() {
            this.showForm = false;
            if ( this.provider.inst_id==1 ) {
                this.inst_name="Entire Consortium";
            } else {
                if (this.is_admin) {
                    this.inst_name = this.institutions[this.provider.inst_id-1].name;
                } else {
                    this.inst_name = this.institutions[0].name;
                }
            }
            if ( this.is_admin || (this.is_manager && this.provider.inst_id==this.user_inst_id)) {
                this.can_edit = true;
            }
            this.status=this.statusvals[this.provider.is_active];
            // Setup form:master_reports and form:connectors
            for(var i=0;i<this.provider.reports.length;i++){
               this.form.master_reports.push(this.provider.reports[i].id);
            }
            for(var i=0;i<this.provider.connectors.length;i++){
               this.form.connectors.push(this.provider.connectors[i].id);
            }
            // Setup DataTable headers array based on the provider connectors
            this.header_fields.forEach((fld) => {
                // Connection fields are setup in "header_fields" as names without labels
                if (fld.label == '' && fld.name != '') {
                    let cnx = this.provider.connectors.find(c => c.name == fld.name);
                    if (typeof(cnx) != 'undefined') {
                        this.headers.push({ text: cnx.label, value: cnx.name});
                    }
                } else {
                    this.headers.push({ text: fld.label, value: fld.name });
                }
            });

            console.log('Provider Component mounted.');
        }
    }
</script>

<style>
.wrap-column-boxes {
    flex-flow: row wrap;
    align-items: flex-end;
 }
</style>
