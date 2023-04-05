<template>
  <div>
    <div class="page-header"><h2>{{ institution.name }}</h2></div>
    <div v-if="(is_admin || is_manager)" class="details">
  	  <v-row class="d-flex ma-0" no-gutters>
        <v-col class="d-flex pa-0">
          <h3 class="section-title">Details &nbsp; &nbsp;</h3>
          <v-icon v-if="!showInstForm" title="Edit Institution Settings" @click="showInstForm=true">mdi-cog-outline</v-icon>
          &nbsp;
          <v-icon v-if="is_admin && mutable_inst.can_delete" title="Delete Institution" @click="destroy(mutable_inst.id)">
            mdi-trash-can-outline
          </v-icon>
        </v-col>
        <div class="status-message" v-if="success || failure">
          <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </div>
      </v-row>
      <!-- display institution form if manager has activated it. onSubmit function closes and resets instForm -->
      <div v-if="showInstForm">
        <form method="POST" action="" @submit.prevent="instFormSubmit" @keydown="instForm.errors.clear($event.target.name)"
              class="in-page-form">
          <v-text-field v-if="is_admin" v-model="instForm.name" label="Name" outlined></v-text-field>
          <v-text-field v-if="is_admin" v-model="instForm.local_id" label="Internal Identifier" outlined></v-text-field>
          <v-switch v-if="is_admin" v-model="instForm.is_active" label="Active?"></v-switch>
		      <div class="field-wrapper">
            <v-subheader v-text="'FTE'"></v-subheader>
            <v-text-field v-model="instForm.fte" label="FTE" hide-details single-line type="number"
            ></v-text-field>
  	      </div>
          <div v-if="is_admin" class="field-wrapper has-label">
            <v-subheader v-text="'Belongs To'"></v-subheader>
            <v-select :items="all_groups" v-model="instForm.institutiongroups" value="mutable_groups"
                      item-text="name" item-value="id" label="Institution Group(s)" multiple
                      chips hint="Assign group membership for this institution" persistent-hint
            ></v-select>
          </div>
          <v-textarea v-model="instForm.notes" value="mutable_inst.notes" label="Notes" auto-grow
          ></v-textarea>
          <v-btn small color="primary" type="submit" :disabled="instForm.errors.any()">
            Save Institution Settings
          </v-btn>
          <v-btn small type="button" @click="showInstForm=false">cancel</v-btn>
        </form>
      </div>
      <!-- Values-only when form not active -->
      <div v-else>
        <v-simple-table dense>
          <tr><td>Name</td><td>{{ mutable_inst.name }}</td></tr>
          <tr><td>Internal ID</td><td>{{ mutable_inst.local_id }}</td></tr>
          <tr><td>Status</td><td>{{ status }}</td></tr>
          <tr><td>FTE</td><td>{{ mutable_inst.fte }}</td></tr>
          <tr>
            <td>Groups</td>
            <td>
              <template v-for="group in all_groups">
                <v-chip v-if="mutable_groups.includes(group.id)">{{ group.name }}</v-chip>
    	        </template>
            </td>
          </tr>
          <tr v-if="mutable_inst.notes"><td>Notes</td><td>{{ mutable_inst.notes }}</td></tr>
        </v-simple-table>
      </div>
    </div>
    <!-- Users for the institution -->
    <div class="users" no-gutters>
      <h3 class="section-title">Users &nbsp; &nbsp;</h3>
      <v-icon v-if="userInputForm!='edit'" title="Add a User" @click="createUser">mdi-account-plus-outline</v-icon>
      <!-- User form is off, display as a list -->
      <div v-if="userInputForm==''">
        <v-data-table :headers="userHeaders" :items="mutable_users" item-key="id">
          <template v-slot:item="{ item }" >
            <tr>
              <td>{{ item.name }}</td>
              <td>{{ item.permission }}&nbsp;</td>
              <td>{{ item.last_login }}</td>
              <td class="dt_action">
                <v-icon title="Edit User Settings" @click="editUser(item.id)">mdi-cog-outline</v-icon>
                &nbsp; &nbsp;
                <v-icon title="Delete User" @click="destroyUser(item.id)">mdi-trash-can-outline</v-icon>
              </td>
            </tr>
          </template>
        </v-data-table>
      </div>
      <!-- User form is on -->
      <div v-else>
        <v-row class="d-flex ma-2">
          <v-col v-if="userInputForm=='edit'"><h4>Edit user settings</h4></v-col>
          <v-col v-else><h4>Create new user</h4></v-col>
        </v-row>
        <v-row class="d-flex ma-0 pa-0" no-gutters>
          <v-col class="d-flex ma-0 pa-0">
            <form method="POST" action="" @submit.prevent="userFormSubmit" @keydown="userForm.errors.clear($event.target.name)"
                  class="in-page-form">
              <v-text-field v-model="userForm.name" label="Name" outlined></v-text-field>
              <v-text-field outlined required name="email" label="Email" type="email"
                            v-model="userForm.email" :rules="emailRules">
              </v-text-field>
              <v-switch v-model="userForm.is_active" label="Active?"></v-switch>
              <v-text-field outlined name="password" label="Password" id="password" type="password"
                            v-model="userForm.password" :rules="passwordRules">
              </v-text-field>
              <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                            type="password" v-model="userForm.confirm_pass" :rules="passwordRules">
              </v-text-field>
      	      <div class="field-wrapper">
      	        <v-subheader v-text="'User Roles'"></v-subheader>
                  <v-select :items="all_roles" v-model="userForm.roles" item-text="name" item-value="id" label="User Role(s)"
                            multiple chips hint="Define roles for user" persistent-hint
      	        ></v-select>
              </div>
              <p>&nbsp;</p>
              <v-btn small color="primary" type="submit" :disabled="userForm.errors.any()">{{ userSubmitLabel}}</v-btn>
              <!-- <v-btn small color="primary" type="submit" :disabled="userForm.errors.any()">Save New User</v-btn> -->
              <v-btn small type="button" @click="hideUserForm">cancel</v-btn>
            </form>
          </v-col>
        </v-row>
      </div>
    </div>
    <!-- Include harvest Log summary table -->
    <div class="related-list">
      <v-expansion-panels><v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Recent Harvest Activity</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <div v-if="harvests.length > 0">
            <harvestlog-summary-table :harvests='harvests' :inst_id="institution.id"></harvestlog-summary-table>
      			></harvestlog-summary-table>
          </div>
          <div v-else>
            <p>No harvest records found for this institution</p>
          </div>
  	    </v-expansion-panel-content>
	    </v-expansion-panel></v-expansion-panels>
    </div>
    <!-- Sushi Settings arranged by-provider -->
    <div class="related-list">
      <h3 class="section-title">Sushi Settings by Provider</h3>
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
      <div v-if="(is_manager || is_admin) && (mutable_unset_con.length > 0 || mutable_unset_glo.length > 0)">
        <form method="POST" action="/sushisettings" @submit.prevent="sushiFormSubmit"
              @keydown="sushiForm.errors.clear($event.target.name)">
          <input v-model="sushiForm.inst_id" id="institution.id" type="hidden">
          <v-row class="d-flex my-2" no-gutters>
            <v-col v-if="is_admin && mutable_unset_con.length > 0" class="d-flex pr-4" cols="4">
              <v-select :items="mutable_unset_con" v-model="sushiForm.prov_id" @change="onUnsetChange('conso')" outlined
                        placeholder="Connect a Consortium Provider" item-text="name" item-value="id" color="primary"
              ></v-select>
            </v-col>
            <v-col v-if="mutable_unset_glo.length > 0" class="d-flex pa-0" cols="4">
              <v-select :items="mutable_unset_glo" v-model="sushiForm.global_id" @change="onUnsetChange('global')" outlined
                        placeholder="Add Institution-Specific Provider" item-text="name" item-value="id" color="primary"
              ></v-select>
            </v-col>
          </v-row>
          <div v-if="showSushiForm" class="form-fields">
            <template v-for="cnx in new_provider.connectors">
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
        <v-data-table :headers="sushiHeaders" :items="mutable_inst.sushiSettings" item-key="id" :key="'setdt_'+dtKey">
          <template v-slot:item="{ item }" >
            <tr>
              <td>
                 <span v-if="item.provider.is_active">
                   <a :href="'/providers/'+item.prov_id">{{ item.provider.name }}</a>
                 </span>
                 <span v-else class="isInactive" @click="goEditProv(item.prov_id)">
                   {{ item.provider.name }}
                 </span>
              </td>
              <td v-if="mutable_connectors['customer_id']['active']">
                <span v-if="item.customer_id=='-missing-'" class="Incomplete">missing+required</span>
                <span v-else>{{ item.customer_id }}</span>
              </td>
              <td v-if="mutable_connectors['requestor_id']['active']">
                <span v-if="item.requestor_id=='-missing-'" class="Incomplete">missing+required</span>
                <span v-else>{{ item.requestor_id }}</span>
              </td>
              <td v-if="mutable_connectors['API_key']['active']">
                <span v-if="item.API_key=='-missing-'" class="Incomplete">missing+required</span>
                <span v-else>{{ item.API_key }}</span>
              </td>
              <td v-if="mutable_connectors['extra_args']['active']">
                <span v-if="item.extra_args=='-missing-'" class="Incomplete">missing+required</span>
                <span v-else>{{ item.extra_args }}</span>
              </td>
              <td :class="item.status">{{ item.status }}</td>
              <td v-if="(is_manager && !item.provider.restricted) || is_admin">
                <span class="dt_action">
                  <v-icon title="Settings and harvests" @click="goEdit(item.id)">mdi-cog-outline</v-icon>
                  &nbsp; &nbsp;
                  <v-icon title="Delete connection" @click="destroySushi(item)">mdi-trash-can-outline</v-icon>
                </span>
              </td>
              <td v-else>&nbsp;</td>
            </tr>
          </template>
        </v-data-table>
      </div>
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
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {institution: { type:Object, default: () => {} },
                users: { type:Array, default: () => [] },
                unset_conso: { type:Array, default: () => [] },
                unset_global: { type:Array, default: () => [] },
                all_connectors: { type:Object, default: () => {} },
                all_groups: { type:Array, default: () => [] },
                all_roles: { type:Array, default: () => [] },
                harvests: { type:Array, default: () => [] }
        },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
                showInstForm: false,
                userInputForm: '',
                importDialog: false,
                showSushiForm: false,
                showTest: false,
                userSubmitLabel: 'Save New User',
                mutable_inst: { ...this.institution },
                mutable_unset_con: [ ...this.unset_conso ],
                mutable_unset_glo: [ ...this.unset_global ],
                mutable_users: [ ...this.users ],
                mutable_groups: [ ...this.institution.groups],
                mutable_connectors: { ...this.all_connectors },
                instForm: new window.Form({
                    name: this.institution.name,
                    local_id: this.institution.local_id,
                    is_active: this.institution.is_active,
                    fte: this.institution.fte,
                    institutiongroups: this.institution.groups,
                    notes: this.institution.notes,
                }),
                new_provider: { 'connectors': [] },
                current_user: {},
                userHeaders: [
                  { text: 'Name ', value: 'name' },
                  { text: 'Permission Level', value: '' },
                  { text: 'Last Login', value: 'last_login' },
                  { text: '', value: ''}
                ],
                emailRules: [
                    v => !!v || 'E-mail is required',
                    v => /.+@.+/.test(v) || 'E-mail must be valid'
                ],
                passwordRules: [
                    v => !!v || 'Password is required',
                    v => v.length >= 8 || 'Password must be at least 8 characters'
                ],
                userForm: new window.Form({
                    name: '',
                    inst_id: this.institution.inst_id,
                    is_active: 1,
                    email: '',
                    password: '',
                    confirm_pass: '',
                    roles: []
                }),
                sushiForm: new window.Form({
                    inst_id: this.institution.id,
                    prov_id: null,
                    global_id: null,
                    customer_id: '',
                    requestor_id: '',
                    API_key: '',
                    extra_args: '',
                    status: 'Enabled'
                }),
                // Actual headers are built/updated by buildsushiHeaders
                sushiHeaders: [],
                dtKey: 1,
                csv_upload: null,
                export_filters: { 'inst': [this.institution.id], 'prov': [], 'group': 0 },
                import_type: '',
                import_types: ['Add or Update', 'Full Replacement']
            }
        },
        methods: {
            instFormSubmit (event) {
                this.success = '';
                this.failure = '';
                this.instForm.patch('/institutions/'+this.institution['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_inst = response.institution;
                            this.status = this.statusvals[this.instForm.is_active];
                            this.mutable_groups = this.instForm.institutiongroups;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.showInstForm = false;
                this.dtKey += 1;
            },
            editUser (userid) {
                this.failure = '';
                this.success = '';
                this.userInputForm = 'edit';
                this.userSubmitLabel = 'Update User';
                this.current_user = this.mutable_users[this.mutable_users.findIndex(u=> u.id == userid)];
                this.userForm.name = this.current_user.name;
                this.userForm.inst_id = this.mutable_inst.id;
                this.userForm.is_active = this.current_user.is_active;
                this.userForm.email = this.current_user.email;
                this.userForm.password = '';
                this.userForm.confirm_pass = '';
                this.userForm.roles = this.current_user.roles;
            },
            createUser () {
                this.failure = '';
                this.success = '';
                this.userInputForm = 'create';
                this.userSubmitLabel = 'Save New User';
                this.userForm.name = '';
                this.userForm.inst_id = this.mutable_inst.id;
                this.userForm.is_active = 1;
                this.userForm.email = '';
                this.userForm.password = '';
                this.userForm.confirm_pass = '';
                this.userForm.roles = [1];
                this.userForm.notes = '';
            },
            hideSushiForm () {
                this.showSushiForm = false;
                this.sushiForm.prov_id = null;
                this.sushiForm.global_id = null;
                this.new_provider = { 'connectors': [] };
            },
            onUnsetChange (type) {
                let _prov = {};
                if (type == 'conso') {
                  _prov = this.mutable_unset_con.find(p => p.id == this.sushiForm.prov_id);
                } else if (type == 'global') {
                  _prov = this.mutable_unset_glo.find(p => p.id == this.sushiForm.global_id);
                } else {
                  this.failure = 'Javascript error : unknown type in onUnsetChange';
                  return;
                }
                this.new_provider = { ..._prov };
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
            enableImportDialog () {
                this.csv_upload = null;
                this.importDialog = true;
            },
            importSubmit (event) {
                this.success = '';
                this.failure = '';
                if (this.import_type == '') {
                    this.failure = 'An import type is required';
                    return;
                }
                if (this.csv_upload==null) {
                    this.failure = 'A CSV import file is required';
                    return;
                }
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
                             this.success = response.data.msg;
                         } else {
                             this.failure = response.data.msg;
                         }
                     });
                this.importDialog = false;
            },
            doExport () {
                let url = "/sushi-export?filters="+JSON.stringify(this.export_filters);
                window.location.assign(url);
            },
            hideUserForm (event) {
                this.userInputForm = '';
			      },
            userFormSubmit (event) {
                this.success = '';
                this.failure = '';
                if (this.userForm.password != this.userForm.confirm_pass) {
                    this.failure = 'Passwords do not match! Please re-enter';
                    return;
                }
                if (this.userInputForm == 'edit') {
                    this.userForm.patch('/users/'+this.current_user.id)
                        .then((response) => {
                            if (response.result) {
                                // Update mutable_users record with newly saved values...
                                var idx = this.mutable_users.findIndex(u => u.id == this.current_user.id);
                                Object.assign(this.mutable_users[idx], response.user);
                                this.success = response.msg;
                            } else {
                                this.failure = response.msg;
                            }
                        });
                } else if (this.userInputForm == 'create') {
                    this.userForm.post('/users')
                        .then((response) => {
                            if (response.result) {
                                this.failure = '';
                                this.success = response.msg;
                                // Add the new user to the mutable array and re-sort it
                                this.mutable_users.push(response.user);
                                this.mutable_users.sort((a,b) => {
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
                this.userInputForm = '';
            },
            testSettings (event) {
                if (!(this.is_admin || this.is_manager)) { return; }
                this.failure = '';
                this.success = '';
                this.testData = '';
                this.testStatus = "... Working ...";
                this.showTest = true;
                var testArgs = {'inst_id' : this.sushiForm.inst_id};
                if (this.sushiForm.requestor_id != '') testArgs['requestor_id'] = this.sushiForm.requestor_id;
                if (this.sushiForm.customer_id != '') testArgs['customer_id'] = this.sushiForm.customer_id;
                if (this.sushiForm.API_key != '') testArgs['API_key'] = this.sushiForm.API_key;
                if (this.sushiForm.extra_args != '') testArgs['extra_args'] = this.sushiForm.extra_args;
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
            sushiFormSubmit (event) {
                this.success = '';
                this.failure = '';
                // All connectors are required - whether they work or not is a matter of testing+confirming
                this.new_provider.connectors.forEach( (cnx) => {
                    if (this.sushiForm[cnx.name] == '' || this.sushiForm[cnx.name] == null) {
                        this.failure = "Error: "+cnx.name+" must be supplied to connect to this provider!";
                    }
                });
                if (this.failure != '') return;

                // crea() method on sushisettings controller to add to the table
                this.sushiForm.post('/sushisettings')
	                .then((response) => {
                      if (response.result) {
                          this.success = response.msg;
                          // Add the new connection to the settings rows and sort it by-name ascending
                          this.mutable_inst.sushiSettings.push(response.setting);
                          this.mutable_inst.sushiSettings.sort((a,b) => {
                            if ( a.provider.name < b.provider.name ) return -1;
                            if ( a.provider.name > b.provider.name ) return 1;
                            return 0;
                          });
                          // Remove the provider from the unset array
                          if (this.sushiForm.global_id == null) {
                            this.mutable_unset_con.splice(this.mutable_unset_con.findIndex(p => p.id==this.new_provider.id),1);
                          } else {
                            this.mutable_unset_glo.splice(this.mutable_unset_glo.findIndex(p => p.id==this.new_provider.id),1);
                          }
                          // Check provider connectors to see if a new connector was just enabled
                          let new_cnx = false;
                          this.new_provider.connectors.forEach( (cnx) => {
                              if (!new_cnx && !this.mutable_connectors[cnx.name]['active']) {
                                this.mutable_connectors[cnx.name]['active'] = true;
                                new_cnx = true;
                              }
                          });
                          // If new connector enabled, rebuild headers
                          if (new_cnx) this.buildsushiHeaders();
                          this.new_provider = { 'connectors': [] };
                          this.showSushiForm = false;
                          this.sushiForm.prov_id = null;
                          this.sushiForm.global_id = null;
                          this.dtKey += 1;
                      } else {
                          this.success = '';
                          this.failure = response.msg;
                      }
	                });
            },
            destroy (instid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting an institution cannot be reversed, only manually recreated."+
                        " Because this institution has no harvested usage data, it can be safely"+
                        " deleted. NOTE: All users and SUSHI settings connected to this institution"+
                        " will also be removed.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/institutions/'+instid)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/institutions");
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
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
                       if (setting.inst_id ==1) { // consortium provider
                           this.mutable_unset_con.push({'id': setting.prov_id, 'name': setting.provider.name});
                           this.mutable_unset_con.sort((a,b) => {
                             if ( a.name < b.name ) return -1;
                             if ( a.name > b.name ) return 1;
                             return 0;
                           });
                       } else { // institution-specific provider
                           this.mutable_unset_glo.push({'id': setting.prov_id, 'name': setting.provider.name});
                           this.mutable_unset_glo.sort((a,b) => {
                             if ( a.name < b.name ) return -1;
                             if ( a.name > b.name ) return 1;
                             return 0;
                           });
                       }
                       // Remove the setting from the "set" list
                       this.mutable_inst.sushiSettings.splice(this.mutable_inst.sushiSettings.findIndex(s=> s.id == setting.id),1);
                       this.dtKey += 1;           // re-render of the datatable
                    }
                })
                .catch({});
            },
            destroyUser (userid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "This user will be permanently deleted along with any saved report views.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/users/'+userid)
                           .then( (response) => {
                               if (response.data.result) {
                                   // Remove the setting from the "set" list
                                   this.mutable_users.splice(this.mutable_users.findIndex(s=> s.id == userid),1);
                                   this.dtKey += 1;           // re-render of the datatable
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                  }
                })
                .catch({});
            },
            goEdit (settingId) {
                window.location.assign('/sushisettings/'+settingId+'/edit');
            },
            goEditProv (provId) {
                window.location.assign('/providers/'+provId+'/edit');
            },
            // Set Sushi DataTable headers array based on the provider connectors
            buildsushiHeaders() {
              this.sushiHeaders = [ { text: 'Name', value: 'name' } ];
              Object.keys(this.mutable_connectors).forEach( (key) => {
                  let cnx = this.mutable_connectors[key];
                  if (cnx.active) this.sushiHeaders.push( { text: cnx.label, value: cnx.name} );
              });
              this.sushiHeaders.push({ text: 'Status', value: 'status' });
              this.sushiHeaders.push({ text: '', value: '' });
            },
        },
        computed: {
          ...mapGetters(['is_manager', 'is_admin'])
        },
        mounted() {

            this.status=this.statusvals[this.institution.is_active];
            this.buildsushiHeaders();

            console.log('Institution Component mounted.');
        }
    }
</script>
<style scoped>
.Enabled { color: #00dd00; }
.Disabled { color: #dd0000; }
.Suspended {
  color: #999999;
  font-style: italic;
}
.Incomplete {
  color: #dd0000;
  font-style: italic;
}
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
</style>
