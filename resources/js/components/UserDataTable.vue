<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="showForm==''">
      <v-btn small color="primary" @click="createForm">Create a User</v-btn>
      <v-row v-if="is_manager || is_admin">
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
            <td><a :href="'/institutions/'+item.inst_id+'/edit'">{{ item.inst_name }}</a></td>
            <td v-if="item.status">Active</td>
            <td>{{ item.email }}</td>
            <td>{{ item.role_string }}</td>
            <td>{{ item.last_login }}</td>
            <td><v-btn small class='btn btn-danger' type="button" @click="destroy(item.id)">Delete</v-btn></td>
          </tr>
        </template>
      </v-data-table>
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
          Save New User
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
          { text: 'Institution', value: 'inst_name' },
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
            is_active: 0,
            email: '',
            password: '',
            confirm_pass: '',
            roles: []
        })
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
                            // Add the new user to the mutable array
                            this.mutable_users.push(response.user);
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
            this.form.is_active = '';
            this.form.email = '';
            this.form.password = '';
            this.form.confirm_pass = '';
            this.form.roles = [];
        },
        hideForm (event) {
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
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
