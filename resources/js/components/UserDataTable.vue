<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col v-if="is_admin" class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="importForm">Import Users</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="createForm">Create a User</v-btn>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col v-if="is_admin" class="d-flex px-2" cols="4">
          <a :href="'/users/export/xlsx'">Export to Excel</a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table :headers="headers" :items="mutable_users" item-key="id" :options="mutable_options"
                    :key="'DT'+dtKey" @update:options="updateOptions">
        <template v-slot:item="{ item }">
          <tr>
            <td><a @click="editForm(item.id)">{{ item.name }}</a></td>
            <td><a :href="'/institutions/'+item.inst_id+'/edit'">{{ item.institution.name }}</a></td>
            <td v-if="item.status">Active</td>
            <td>{{ item.email }}</td>
            <td>{{ item.role_string }}</td>
            <td>{{ item.last_login }}</td>
            <td><v-btn small class='btn btn-danger' type="button" @click="destroy(item.id)">Delete</v-btn></td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <v-dialog v-model="importDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Users</v-card-title>
        <v-spacer></v-spacer>
        <v-card-subtitle><strong>NOTE: Users cannot be deleted during an import operation.</strong></v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <v-layout wrap>
              <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
              <p>
                Use caution when using this import function. Password fields will be encrypted when they are saved.
                The <strong>import source file</strong>, however, could contain clear-text user passwords as CSV
                values. Protecting or deleting this file after a successful import is recommended to help prevent
                unauthorized access to the data and settings for your consortium.
              </p>
              <p><strong>User imports operate as both "Add" and "Update".</strong><br />
                If an ID in column-1 of the import file matches an existing user -OR- if the ID does not match any
                existing user, but the email in column-2 does match an existing user, the import will update that user.
                Otherwise, the import will perform an "Add" operation. Any import row with an empty or non-existent
                institution ID in column-9 will be ignored.</li>
              </p>
              <ul><strong>Updating users</strong>:
                <li>Updates will overrwite all fields for the user, with the possible exception of the password, with
                    the values in the import file.
                <li>Import rows (with a matching ID) that attempt to set an existing user's email to a value already
                    defined for another user will result in an unchanged email address and other values updated.</li>
                <li>Rows with a blank or empty password value will result in an unchanged password and the other fields
                    updated.
                </li>
              </ul>
              <ul><strong>Adding users</strong>:
                <li>The surest way to add users is to assign new, sequentially increasing values in the column-1 (ID),
                    and a unique email address in column-2.</li>
                <li>Import rows that attempt to add a user with an email field value that matches the email address for
                    another user will be ignored.</li>
                <li>Rows with a blank or empty password values will be ignored.</li>
              </ul>
            </v-layout>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit" :disabled="csv_upload==null">
                Run Import
            </v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="userDialog" persistent max-width="800px">
      <v-card>
        <v-card-title>
          <span v-if="dialogType=='edit'">Edit user settings</span>
          <span v-else>Create a new user</span>
        </v-card-title>
        <v-form class="in-page-form" :key="'UFrm'+form_key">
          <v-card-text>
            <div class="status-message" v-if="user_success || user_failure">
              <span v-if="user_success" class="good" role="alert" v-text="user_success"></span>
              <span v-if="user_failure" class="fail" role="alert" v-text="user_failure"></span>
            </div>
            <v-container grid-list-md>
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-text-field outlined required name="email" label="Email" type="email"
                            v-model="form.email" :rules="emailRules">
              </v-text-field>
              <v-row class="d-flex ma-0" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">
                      <v-switch v-model="form.is_active" label="Active?"></v-switch>
                  </v-col>
                  <v-col class="d-flex pa-0" cols="3">
                      <v-btn small color="primary" @click="createInst">Create an Institution</v-btn>
                  </v-col>
              </v-row>
              <div v-if="is_admin">
                  <v-select outlined required :items="mutable_institutions" v-model="form.inst_id" item-value="id"
                            item-text="name" value="current_user.inst_id" label="Institution" @change="changeInst"
                  ></v-select>
              </div>
              <div v-else>
                <v-text-field outlined readonly label="Institution" :value="inst_name"></v-text-field>
              </div>
              <v-text-field outlined name="password" label="Password" id="password" type="password"
                            v-model="form.password" :rules="passwordRules">
              </v-text-field>
              <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                            type="password" v-model="form.confirm_pass" :rules="passwordRules">
              </v-text-field>
              <div v-if="is_manager || is_admin" class="field-wrapper">
      	        <v-subheader v-text="'User Roles'"></v-subheader>
        	    <v-select :items="allowed_roles" v-model="form.roles" :value="current_user.roles" item-text="name"
         	              item-value="id" label="User Role(s)" multiple chips hint="Define roles for user"
         	              persistent-hint
        	    ></v-select>
                <div style="display: inline-block;">
                  Roles<br>
                  Admin: can create and manage settings for all users, institutions, and providers<br>
                  Manager: can manage settings for their own institutions and can create and manage users within their institution<br>
                  User: can view statistics for their own institution<br>
                  Viewer: can view statistics for all institutions
                </div>
              </div>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-col class="d-flex">
              <v-btn small color="primary" type="button" @click="formSubmit">Save User</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="userDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>
    <v-dialog v-model="instDialog" persistent max-width="500px">
      <v-card>
        <v-card-title>
          <span>Create a new institution</span>
        </v-card-title>
        <v-form class="in-page-form">
          <v-card-text>
            <div class="status-message" v-if="inst_failure">
              <span v-if="inst_failure" class="fail" role="alert" v-text="inst_failure"></span>
            </div>
            <v-container grid-list-md>
              <v-text-field v-model="instForm.name" label="Name" outlined></v-text-field>
              <v-text-field v-model="instForm.local_id" label="Local Identifier" outlined></v-text-field>
              <v-switch v-model="instForm.is_active" label="Active?"></v-switch>
              <div class="field-wrapper">
                <v-subheader v-text="'FTE'"></v-subheader>
                <v-text-field v-model="instForm.fte" label="FTE" hide-details single-line type="number"></v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Belongs To'"></v-subheader>
                <v-select :items="all_groups" v-model="instForm.institutiongroups" item-text="name" item-value="id"
                          label="Institution Group(s)" multiple chips persistent-hint
                          hint="Assign group membership for this institution"
                ></v-select>
              </div>
              <v-textarea v-model="instForm.notes" label="Notes" auto-grow></v-textarea>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn small color="primary" type="button" @click="submitNewInst">Save Institution</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="instDialog=false">Cancel</v-btn>
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
  import axios from 'axios';
  export default {
    props: {
            users: { type:Array, default: () => [] },
            allowed_roles: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        user_success: '',
        user_failure: '',
        inst_failure: '',
        inst_name: '',
        mutable_users: [ ...this.users ],
        mutable_institutions: [ ...this.institutions ],
        current_user: {},
        dialogType: 'create',
        userDialog: false,
        instDialog: false,
        importDialog: false,
        form_key: 1,
        headers: [
          { text: 'User Name ', value: 'name' },
          { text: 'Institution', value: 'institution.name' },
          { text: 'Status', value: 'status' },
          { text: 'Email', value: 'email' },
          { text: 'Roles', value: 'role_string' },
          { text: 'Last Login', value: 'last_login' },
        ],
        emailRules: [
            v => !!v || 'E-mail is required',
            v => /.+@.+/.test(v) || 'E-mail must be valid'
        ],
        form: new window.Form({
            name: '',
            inst_id: null,
            is_active: 1,
            email: '',
            password: '',
            confirm_pass: '',
            roles: []
        }),
        instForm: new window.Form({
            name: '',
            local_id: '',
            is_active: 1,
            fte: 0,
            institutiongroups: [],
            notes: '',
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
      }
    },
    methods: {
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.user_failure = '';
            if (this.form.password != this.form.confirm_pass) {
                this.user_failure = 'Passwords do not match! Please re-enter';
                return;
            }
            if (this.dialogType == 'edit') {
                if  (this.form.password.length>0 && this.form.password.length<8) {
                    this.user_failure = 'Password must be at least 8 characters';
                    return;
                }
                this.form.patch('/users/'+this.current_user.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_users record with newly saved values...
                            var idx = this.mutable_users.findIndex(u => u.id == this.current_user.id);
                            Object.assign(this.mutable_users[idx], response.user);
                            this.success = response.msg;
                            this.userDialog = false;
                        } else {
                            this.user_failure = response.msg;
                        }
                    });
            } else if (this.dialogType == 'create') {
                this.form.post('/users')
                    .then((response) => {
                        if (response.result) {
                            this.success = response.msg;
                            // Add the new user to the mutable array and re-sort it
                            this.mutable_users.push(response.user);
                            this.mutable_users.sort((a,b) => {
                              if ( a.name < b.name ) return -1;
                              if ( a.name > b.name ) return 1;
                              return 0;
                            });
                            this.dtKey += 1;           // force re-render of the datatable
                            this.userDialog = false;
                        } else {
                            this.success = '';
                            this.user_failure = response.msg;
                        }
                    });
            }
        },
        destroy (userid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "All information related to this user will also be deleted!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/users/'+userid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               this.mutable_users.splice(this.mutable_users.findIndex(u=> u.id == userid),1);
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
        submitNewInst (event) {
            // report errors to the inst-dialog and success to the user-dialog
            this.user_success = '';
            this.inst_failure = '';
            this.instForm.post('/institutions')
                .then( (response) => {
                    if (response.result) {
                        this.inst_failure = '';
                        this.user_success = response.msg;
                        // Add the new institution onto the mutable array and re-sort it
                        this.mutable_institutions.push(response.institution);
                        this.mutable_institutions.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                        // apply new new institution ID to the user form; don't change if already set
                        if (this.form.inst_id == null) {
                            this.form.inst_id = response.institution.id;
                            this.form_key += 1;
                        }
                        this.instDialog = false;
                    } else {
                        this.user_success = '';
                        this.inst_failure = response.msg;
                    }
                });
        },
        importForm () {
            this.csv_upload = null;
            this.importDialog = true;
            this.userDialog = false;
        },
        editForm (userid) {
            this.failure = '';
            this.success = '';
            this.user_success = '';
            this.user_failure = '';
            this.dialogType = "edit";
            this.current_user = this.mutable_users[this.mutable_users.findIndex(u=> u.id == userid)];
            this.form.name = this.current_user.name;
            this.form.inst_id = this.current_user.inst_id;
            this.form.is_active = this.current_user.is_active;
            this.form.email = this.current_user.email;
            this.form.password = '';
            this.form.confirm_pass = '';
            this.form.roles = this.current_user.roles;
            this.userDialog = true;
            this.importDialog = false;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.user_success = '';
            this.user_failure = '';
            this.dialogType = "create";
            var _inst = (this.is_admin) ? null : this.institutions[0].id;
            this.current_user = {roles: [1], inst_id: _inst};
            this.form.name = '';
            this.form.inst_id = _inst;
            this.form.is_active = 1;
            this.form.email = '';
            this.form.password = '';
            this.form.confirm_pass = '';
            this.form.roles = [1];
            this.userDialog = true;
            this.importDialog = false;
        },
        createInst () {
            this.failure = '';
            this.success = '';
            this.instForm.name = '';
            this.instForm.local_id = '';
            this.instForm.is_active = 1;
            this.instForm.fte = 0;
            this.instForm.institutiongroups = [];
            this.instForm.notes = '';
            this.instDialog = true;
        },
        importSubmit (event) {
            this.success = '';
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/users/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response users
                         this.mutable_users = response.data.users;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.importDialog = false;
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
        changeInst () {
            let view_role = this.allowed_roles.find(r => r.name == "Viewer");
            if (!view_role) return;

            // Assigning to consortium staff turns on Viewer role
            if (this.form.inst_id == 1) {
                if (!this.form.roles.includes(view_role.id)) this.form.roles.push(view_role.id);
            // Assigning to a non-consortium staff inst turns Viewer role OFF in the form if the user does not have it already
            // (in case set to consortium staff and then change to another before submitting)
            } else {
                let _user = this.users.find(u => u.id == this.current_user.id);
                if (_user) {
                    if (!_user.roles.includes(view_role.id) && this.form.roles.includes(view_role.id)) {
                        this.form.roles.splice(this.form.roles.indexOf(view_role.id), 1);
                    }
                }
            }
        },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin','datatable_options']),
      passwordRules() {
          if (this.dialogType == 'create') {
              return [ v => !!v || 'Password is required',
                       v => v.length >= 8 || 'Password must be at least 8 characters'
                     ];
          // formSubmit handles password validation for edit
          } else {
              return [];
          }
      }
    },
    beforeCreate() {
        // Load existing store data
		this.$store.commit('initialiseStore');
	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','users');
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

      console.log('UserData Component mounted.');
    }
  }
</script>

<style>

</style>
