<template>
  <div>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="showForm==''">
      <v-row>
        <v-col v-if="is_admin" cols="2"><v-btn small color="primary" @click="importForm">Import Users</v-btn></v-col>
        <v-col><v-btn small color="primary" @click="createForm">Create a User</v-btn></v-col>
      </v-row>
      <v-row>
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/users/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/users/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <v-data-table :headers="headers" :items="mutable_users" item-key="id" class="elevation-1">
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
    <div v-else-if="showForm=='import'" style="width:50%; display:inline-block;">
      <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined></v-file-input>
      <p>
        <strong>Users cannot be deleted during an import operation.</strong><br />
      </p>
      <p>
        Use caution when using this import function. Password fields will be encrypted when they are saved in the
        database. The <strong>import source file</strong>, however, could contain clear-text user passwords as CSV
        values. Protecting or deleting this file after a successful import is recommended to help prevent
        unauthorized access to the data and settings for your consortium.
      </p>
      <p><strong>User imports operate as both "Add" and "Update".</strong><br />
        If an ID in column-1 of the import file matches an existing user -OR- if the ID does not match any existing
        user, but the email in column-2 does match an existing user, the import will update that user. Otherwise,
        the import will perform an "Add" operation. Any import row with an empty or non-existent institution ID in
        column-9 will be ignored.</li>
      </p>
      <ul><strong>Updating users</strong>:
        <li>Updates will overrwite all fields for the user, with the possible exception of the password, with the
            values in the import file.
        <li>Import rows (with a matching ID) that attempt to set an existing user's email to a value already defined
            for another user will result in an unchanged email address and the other values updated.</li>
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
      <v-btn small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
      <v-btn small type="button" @click="hideForm">cancel</v-btn>
    </div>
    <div v-else style="width:50%; display:inline-block;">
      <div v-if="showForm=='edit'">
          <h4>Edit user settings</h4>
      </div>
      <div v-else>
          <h4>Create a new user</h4>
      </div>
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-text-field outlined required name="email" label="Email" type="email"
                      v-model="form.email" :rules="emailRules">
        </v-text-field>
        <v-switch v-model="form.is_active" label="Active?"></v-switch>
        <div v-if="is_admin">
            <v-select outlined required :items="institutions" v-model="form.inst_id" value="current_user.inst_id"
                      label="Institution" item-text="name" item-value="id"
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
	      <v-select :items="all_roles" v-model="form.roles" :value="current_user.roles" item-text="name"
 	                item-value="id" label="User Role(s)" multiple chips hint="Define roles for user"
 	                persistent-hint
	      ></v-select>
		</div>
        <p>&nbsp;</p>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
          Save User
        </v-btn>
		<v-btn small type="button" @click="hideForm">cancel</v-btn>
      </form>
    </div>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
            users: { type:Array, default: () => [] },
            all_roles: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        inst_name: '',
        mutable_users: this.users,
        current_user: {},
        showForm: '',
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
        passwordRules: [
            v => !!v || 'Password is required',
            v => v.length >= 8 || 'Password must be at least 8 characters'
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
        csv_upload: null,
      }
    },
    methods: {
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            if (this.form.password != this.form.confirm_pass) {
                this.failure = 'Passwords do not match! Please re-enter';
                return;
            }
            if (this.showForm == 'edit') {
                this.form.patch('/users/'+this.current_user.id)
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
            } else if (this.showForm == 'create') {
                this.form.post('/users')
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
            this.showForm = '';
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
        importForm () {
            this.csv_upload = null;
            this.showForm = 'import';
        },
        editForm (userid) {
            this.failure = '';
            this.success = '';
            this.showForm = "edit";
            this.current_user = this.mutable_users[this.mutable_users.findIndex(u=> u.id == userid)];
            this.form.name = this.current_user.name;
            this.form.inst_id = this.current_user.inst_id;
            this.form.is_active = this.current_user.is_active;
            this.form.email = this.current_user.email;
            this.form.password = '';
            this.form.confirm_pass = '';
            this.form.roles = this.current_user.roles;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.showForm = "create";
            var _inst = (this.is_admin) ? null : this.institutions[0].id;
            this.current_user = {roles: [], inst_id: _inst};
            this.form.name = '';
            this.form.inst_id = _inst;
            this.form.is_active = 1;
            this.form.email = '';
            this.form.password = '';
            this.form.confirm_pass = '';
            this.form.roles = [];
        },
        hideForm (event) {
            this.showForm = '';
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
            this.showForm = '';
        },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin'])
    },
    mounted() {
      if (!this.is_admin) {
          this.inst_name = this.institutions[0].name;
      }
      console.log('UserData Component mounted.');
    }
  }
</script>

<style>

</style>
