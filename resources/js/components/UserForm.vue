<template>
  <div>
    <div v-if="!showForm">
      <v-row>
        <v-col>
    	  <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
        </v-col>
        <v-col v-if="is_manager">
          <v-btn class='btn btn-danger' small type="button" @click="destroy(user.id)">Delete</v-btn>
        </v-col>
      </v-row>
	  <div class="status-message" v-if="success || failure">
		  <span v-if="success" class="good" role="alert" v-text="success"></span>
		  <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
	  </div>
    </div>
	<div v-if="!showForm">
      <v-row>
        <v-col>
          <v-simple-table>
            <tr>
              <td>Name </td>
  	          <td>{{ mutable_user.name }}</td>
  	        </tr>
	        <tr>
	          <td>Email </td>
	          <td>{{ mutable_user.email }}</td>
	        </tr>
            <tr>
              <td>Roles</td>
              <td>
                <template v-for="role in all_roles">
                  <v-chip v-if="mutable_user.roles.includes(role.id)">{{ role.name }}</v-chip>
                </template>
              </td>
            </tr>
          </v-simple-table>
        </v-col>
      </v-row>
    </div>
    <div v-else>
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-text-field outlined required name="email" label="Email" type="email"
                      v-model="form.email" :rules="emailRules">
        </v-text-field>
        <v-switch v-if="is_manager || is_admin" v-model="form.is_active" label="Active?"></v-switch>
        <v-select v-if="is_admin" outlined required :items="institutions" v-model="form.inst_id"
                  label="Institution" item-text="name" item-value="id"
        ></v-select>
        <v-text-field v-else outlined readonly label="Institution" :value="inst_name"></v-text-field>
        <input type="hidden" id="inst_id" name="inst_id" :value="user.inst_id">
        <v-text-field outlined name="password" label="Password" id="password" type="password"
                      v-model="form.password" :rules="passwordRules">
        </v-text-field>
        <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                      type="password" v-model="form.confirm_pass" :rules="passwordRules">
        </v-text-field>
  		<div  v-if="is_manager || is_admin" class="field-wrapper">
	      <v-subheader v-text="'User Roles'"></v-subheader>
	      <v-select :items="all_roles" v-model="form.roles" item-text="name" item-value="id" label="User Role(s)"
 	                multiple chips hint="Define roles for user" persistent-hint
	      ></v-select>
		</div>
        <v-spacer></v-spacer>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
          Save User Settings
        </v-btn>
		<v-btn small type="button" @click="hideForm">cancel</v-btn>
		<div class="status-message" v-if="success || failure">
	        <span v-if="success" class="good" role="alert" v-text="success"></span>
	        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
		</div>
      </form>
    </div>
  </div>
</template>

<script>
 	import Swal from 'sweetalert2';
    import { mapGetters } from 'vuex'
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                user: { type:Object, default: () => {} },
                all_roles: { type:Array, default: () => [] },
                institutions: { type:Array, default: () => [] },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
				showForm: false,
                inst_name: '',
                email: '',
                password: '',
                mutable_user: this.user,
                emailRules: [
                    v => !!v || 'E-mail is required',
                    v => /.+@.+/.test(v) || 'E-mail must be valid'
                ],
                passwordRules: [
                    v => !!v || 'Password is required',
                    v => v.length >= 8 || 'Password must be at least 8 characters'
                ],
                form: new window.Form({
                    name: this.user.name,
                    inst_id: this.user.inst_id,
                    is_active: this.user.is_active,
                    email: this.user.email,
                    password: '',
                    confirm_pass: '',
                    roles: this.user.roles
                })
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                if (this.form.password!=this.form.confirm_pass) {
                    this.failure = 'Passwords do not match! Please re-enter';
                    return;
                }
                this.form.patch('/users/'+this.user['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.mutable_user = response.user;
                            this.success = response.msg;
                        } else {
                            this.failure = response.msg;
                        }
                    });
					this.showForm = false;
            },
            destroy (userid) {
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
                                   window.location.assign("/users");
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
	        swapForm (event) {
	            this.showForm = true;
			},
	        hideForm (event) {
	            this.showForm = false;
			},
        },
        computed: {
          ...mapGetters(['is_manager','is_admin'])
        },
        mounted() {
            if (!this.is_admin) {
                var user_inst=this.institutions[0];
                this.inst_name = user_inst.name;
            }

            this.status=this.statusvals[this.user.is_active];
            console.log('User Component mounted.');
        }
    }
</script>

<style>

</style>
