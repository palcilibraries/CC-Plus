<template>
  <div class="users" no-gutters>
    <v-row no-gutters>
      <h2 class="section-title">Users</h2>
      <v-col v-if="showForm==''">
        <v-btn class="section-action" small color="primary" @click="createForm">Add user</v-btn>
      </v-col>
	  <div class="status-message" v-if="success || failure">
  		<span v-if="success" class="good" role="alert" v-text="success"></span>
  		<span v-if="failure" class="fail" role="alert" v-text="failure"></span>
	  </div>
    </v-row>
    <v-row v-if="showForm==''">
      <v-col>
        <v-data-table :headers="headers" :items="mutable_users" item-key="id" class="elevation-1">
          <template v-slot:item="{ item }" >
            <tr>
              <td><a @click="editForm(item.id)">{{ item.name }}</a></td>
              <td>{{ item.permission }}&nbsp;</td>
              <td>{{ item.last_login }}</td>
              <!--<td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete</v-btn></td>-->
            </tr>
          </template>
        </v-data-table>
      </v-col>
    </v-row>
    <v-row v-else class="d-flex ma-2">
      <v-col v-if="showForm=='edit'"><h4>Edit user settings</h4></v-col>
      <v-col v-else><h4>Create new user</h4></v-col>
    </v-row>
    <v-row v-if="showForm!=''" class="d-flex ma-0 pa-0" no-gutters>
      <v-col class="d-flex ma-0 pa-0">
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          <v-text-field outlined required name="email" label="Email" type="email"
                        v-model="form.email" :rules="emailRules">
          </v-text-field>
          <v-switch v-model="form.is_active" label="Active?"></v-switch>
          <v-text-field outlined name="password" label="Password" id="password" type="password"
                        v-model="form.password" :rules="passwordRules">
          </v-text-field>
          <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                        type="password" v-model="form.confirm_pass" :rules="passwordRules">
          </v-text-field>
  	      <div class="field-wrapper">
	        <v-subheader v-text="'User Roles'"></v-subheader>
            <v-select :items="all_roles" v-model="form.roles" :value="current_user.roles" item-text="name"
 	                  item-value="id" label="User Role(s)" multiple chips hint="Define roles for user"
 	                  persistent-hint
	        ></v-select>
		  </div>
          <p>&nbsp;</p>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save New User</v-btn>
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
      </v-col>
    </v-row>
</div>
</template>

<script>
    import Swal from 'sweetalert2';
    import axios from 'axios';
    export default {
        props: {
                users: { type:Array, default: () => [] },
                inst_id: { type:Number, default: 0 },
                all_roles: { type:Array, default: () => [] },
               },
        data() {
            return {
                success: '',
                failure: '',
                showForm: '',
                mutable_users: this.users,
                current_user: {},
                headers: [
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
                form: new window.Form({
                    name: '',
                    inst_id: this.inst_id,
                    is_active: 1,
                    email: '',
                    password: '',
                    confirm_pass: '',
                    roles: []
                })
            }
        },
        methods: {
            editForm (userid) {
                this.failure = '';
                this.success = '';
                this.showForm = "edit";
                this.current_user = this.mutable_users[this.mutable_users.findIndex(u=> u.id == userid)];
                this.form.name = this.current_user.name;
                this.form.inst_id = this.inst_id;
                this.form.is_active = this.current_user.is_active;
                this.form.email = this.current_user.email;
                this.form.password = '';
                this.form.confirm_pass = '';
                this.form.roles = this.current_user.roles;
            },
            createForm () {
                this.failure = '';
                this.success = '';
                this.showForm = 'create';
                this.form.name = '';
                this.form.inst_id = this.inst_id;
                this.form.is_active = 1;
                this.form.email = '';
                this.form.password = '';
                this.form.confirm_pass = '';
                this.form.roles = [];
                this.form.notes = '';
            },
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
            hideForm (event) {
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
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                       this.mutable_users.splice(this.mutable_users.findIndex(u=> u.id == userid),1);
                  }
                })
                .catch({});
            },
        },
        mounted() {
            console.log('User Component mounted.');
        }
    }
</script>
