<template>
  <div>
    <div class="details" :key="'details_'+dtKey">
      <v-row v-if="can_edit && !showForm" no-gutters>
        <v-col class="d-flex pa-0">
          <h1 class="section-title">Details &nbsp; &nbsp;</h1>
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
	    <v-simple-table v-if="!showForm" dense>
        <tr><td>Name</td><td>{{ mutable_prov.name }}</td></tr>
        <tr><td>Status</td><td>{{ status }}</td></tr>
        <tr v-if="is_admin"><td>Restricted?</td><td>{{ restrictYN[mutable_prov.restricted] }}</td></tr>
	      <tr><td>Serves</td><td>{{ inst_name }}</td></tr>
	      <tr><td>SUSHI service URL</td><td>{{ mutable_prov.server_url_r5 }}</td></tr>
        <tr>
	        <td>Required Connection Fields</td>
	        <td>
	          <template v-for="cnx in connectors">
              <v-chip>{{ cnx.name }}</v-chip>
            </template>
	        </td>
	      </tr>
	      <tr><td>Run harvests monthly on day</td><td>{{ mutable_prov.day_of_month }}</td></tr>
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
      <v-form v-if="showForm" v-model="formValid" class="in-page-form">
        <v-row class="d-flex ma-0">
          <v-col class="d-flex px-1" cols="9">
            <v-text-field v-model="form.name" label="Name" outlined readonly></v-text-field>
          </v-col>
          <v-col class="d-flex px-1" cols="3">
            <v-switch v-model="form.is_active" label="Active?"></v-switch>
          </v-col>
        </v-row>
        <v-row v-if="is_admin" class="d-flex ma-0">
          <v-col class="d-flex pa-0">
            <v-switch v-model="form.restricted" label="Prevent Local Admins from modifying this provider" class="v-input--reverse"
            ></v-switch>
          </v-col>
        </v-row>
        <v-row class="d-flex mt-1">
          <v-col class="d-flex px-1" cols="9">
            <v-select v-if="is_admin && mutable_prov.inst_id!=1" :items="institutions" v-model="form.inst_id"
                      value="provider.inst_id" label="Serves" item-text="name" item-value="id" outlined
            ></v-select>
          </v-col>
        </v-row>
        <v-row class="d-flex ma-0">
          <v-col class="d-flex px-6" col="4"><strong>Reports to Harvest</strong></v-col>
          <v-col class="d-flex px-6" col="4"><strong>Run Harvests Monthly on Day</strong></v-col>
        </v-row>
        <v-row class="d-flex ma-0">
          <v-col class="d-flex px-4" col="6">
              <v-list class="shaded" dense>
                <v-list-item v-for="rpt in master_reports" :key="rpt.name" class="verydense">
                  <v-checkbox :value="form.report_state[rpt.name]" :key="rpt.name" :label="rpt.name"
                              v-model="form.report_state[rpt.name]" dense>
                  </v-checkbox>
                </v-list-item>
              </v-list>
              <div class="float-none"></div>
          </v-col>
          <v-col class="d-flex px-4" col="6">
            <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line dense type="number"
                          class="centered-input" :rules="dayRules"
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row class="d-flex ma-0">
          <v-col class="d-flex pa-0" col="6">
            <v-btn small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
                Save Provider Settings
            </v-btn>
            &nbsp; &nbsp;
            <v-btn small type="button" @click="showForm=false">cancel</v-btn>
          </v-col>
        </v-row>
      </v-form>
    </div>
    <div class="related-list">
      <v-expansion-panels><v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Recent Harvest Activity</h2>
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
      <h2 v-if="is_admin" class="section-title">Sushi Settings by Institution </h2>
      <h2 v-else class="section-title">Sushi Settings</h2>
      <div v-if="is_admin">
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
                      :placeholder="unset_hint" item-text="name" item-value="id" outlined
            ></v-select>
          </v-col>
          <div v-if="showSushiForm" class="form-fields">
            <template v-for="cnx in connectors">
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
            <td>
               <span v-if="item.institution.is_active">
                 <a :href="'/institutions/'+item.inst_id">{{ item.institution.name }}</a>
               </span>
               <span v-else class="isInactive" @click="goEditInst(item.inst_id)">
                 {{ item.institution.name }}
               </span>
            </td>
            <td v-if="connectors.some(c => c.name === 'customer_id')">
              <span v-if="item.customer_id=='-missing-'" class="Incomplete"><em>required</em></span>
              <span v-else>{{ item.customer_id }}</span>
            </td>
            <td v-if="connectors.some(c => c.name === 'requestor_id')">
              <span v-if="item.requestor_id=='-missing-'" class="Incomplete"><em>required</em></span>
              <span v-else>{{ item.requestor_id }}</span>
            </td>
            <td v-if="connectors.some(c => c.name === 'api_key')">
              <span v-if="item.api_key=='-missing-'" class="Incomplete"><em>required</em></span>
              <span v-else>{{ item.api_key }}</span>
            </td>
            <td v-if="connectors.some(c => c.name === 'extra_args')">
              <span v-if="item.extra_args=='-missing-'" class="Incomplete"><em>required</em></span>
              <span v-else>{{ item.extra_args }}</span>
            </td>
            <td :class="item.status">{{ item.status }}</td>
            <td class="dt_action" v-if="is_manager || is_admin">
              <v-icon title="Settings and harvests" @click="goEditSushi(item.id)">mdi-cog-outline</v-icon>
              &nbsp; &nbsp;
              <v-icon title="Delete connection" @click="destroySushi(item)">mdi-trash-can-outline</v-icon>
            </td>
          </tr>
        </template>
        <tr v-if="is_manager || is_admin"><td colspan="6">&nbsp;</td></tr>
        <tr v-else><td colspan="4">&nbsp;</td></tr>
      </v-data-table>
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
                connectors: { type:Array, default: () => [] },
                harvests: { type:Array, default: () => [] },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
                restrictYN: ['No','Yes'],
                inst_name: 'Entire Consortium',
                testData: '',
                testStatus: '',
                can_edit: false,
                showForm: false,
                showTest: false,
                showSushiForm: false,
                importDialog: false,
                csv_upload: null,
                unset_hint: 'Connect an Institution',
                export_filters: { 'inst': [], 'prov': [this.provider.id], 'group': 0 },
                mutable_prov: { ...this.provider },
                mutable_unset: [ ...this.unset ],
                dtKey: 1,
                // Actual headers are built from these in mounted()
                headers: [],
                header_fields: [
                  { label: 'Name ', name: 'name' },
                  { label: '', name: 'customer_id' },
                  { label: '', name: 'requestor_id' },
                  { label: '', name: 'api_key' },
                  { label: '', name: 'extra_args' },
                  { label: 'Status', name: 'status' },
				          { label: '', name: ''}
                ],
                form: new window.Form({
                    name: this.provider.name,
                    inst_id: this.provider.inst_id,
                    day_of_month: this.provider.day_of_month,
                    is_active: this.provider.is_active,
                    restricted: this.provider.restricted,
                    report_state: this.provider.report_state,
                }),
                sushiForm: new window.Form({
                    inst_id: null,
                    prov_id: this.provider.id,
                    customer_id: '',
                    requestor_id: '',
                    api_key: '',
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
              this.success = '';
              this.failure = '';
              // All connectors are required - whether they work or not is a matter of testing+confirming
              this.connectors.forEach( (cnx) => {
                  if (this.sushiForm[cnx.name] == '' || this.sushiForm[cnx.name] == null) {
                      this.failure = "Error: "+cnx.name+" must be supplied to connect to this provider!";
                  }
              });
              if (this.failure != '') return;
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
                          this.sushiForm.api_key = '';
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
                  if (this.connectors.some(c => c.name === 'requestor_id'))
                      testArgs['requestor_id'] = this.sushiForm.requestor_id;
                  if (this.connectors.some(c => c.name === 'customer_id'))
                      testArgs['customer_id'] = this.sushiForm.customer_id;
                  if (this.connectors.some(c => c.name === 'api_key'))
                      testArgs['api_key'] = this.sushiForm.api_key;
                  if (this.connectors.some(c => c.name === 'extra_args'))
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
                  this.sushiForm.api_key = '';
                  this.sushiForm.extra_args = '';
                  this.failure = '';
                  this.success = '';
                  this.testData = '';
                  this.testStatus = '';
                  this.showSushiForm = true;
              },
              goEditSushi (settingId) {
                  window.location.assign('/sushisettings/'+settingId+'/edit');
              },
              goEditInst (instId) {
                  window.location.assign('/institutions/'+instId);
              },
        },
        computed: {
          ...mapGetters(['is_manager','is_admin','user_inst_id'])
        },
        mounted() {
            this.showForm = false;
            if ( this.provider.inst_id!=1 ) {
                let _inst = this.institutions.find(inst => inst.id == this.provider.inst_id);
                this.inst_name = _inst.name;
            }
            if ( this.is_admin || (this.is_manager && this.provider.inst_id==this.user_inst_id)) {
                this.can_edit = true;
            }
            this.status=this.statusvals[this.provider.is_active];
            // Setup DataTable headers array based on the provider connectors
            this.header_fields.forEach((fld) => {
                // Connection fields are setup in "header_fields" as names without labels
                if (fld.label == '' && fld.name != '') {
                    let cnx = this.connectors.find(c => c.name == fld.name);
                    if (typeof(cnx) != 'undefined') {
                        this.headers.push({ text: cnx.label, value: cnx.name});
                    }
                } else {
                    this.headers.push({ text: fld.label, value: fld.name });
                }
            });
            if (!this.is_admin && this.is_manager) {
                this.unset_hint = "'Connect My Institution';"
            }

            console.log('Provider Component mounted.');
        }
    }
//.a.Inactive {

</script>

<style scoped>
.wrap-column-boxes {
    flex-flow: row wrap;
    align-items: flex-end;
}
.Enabled { color: #00dd00; }
.Disabled { color: #dd0000; }
.Suspended {
  color: #999999;
  font-style: italic;
}
.Incomplete {
  color: #ff9900;
  font-style: italic;
}
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
.shaded {
  background-color: #efefef;
}
.verydense {
  max-height: 16px;
  background-color: #efefef;
}
.centered-input >>> input {
  text-align: center;
}
</style>
