<template>
  <div class="details">
  
	  <div class="page-action" v-if="is_admin">
		  <template v-if="is_manager && !showForm">
			<v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
		    <span class="form-good" role="alert" v-text="success"></span>
		    <span class="form-fail" role="alert" v-text="failure"></span>
		  </template>
		  <v-btn class='btn btn-danger' small type="button" @click="destroy(user.id)">Delete</v-btn>
	  </div>
	  
	  <div>
        <div v-if="!showForm">
	      <v-simple-table>
	        <tr>
	          <td>Name </td>
			  <td>{{ $user->name }}</td>
			</tr>
			<tr>
		       <td>Email </td>
		       <td>{{ $user->email }}</td>
		    </tr>
			<tr>
				<td>Roles </td>
				<td>
		              @if(!empty($user->roles()->pluck('name')))
		                  @foreach($user->roles()->pluck('name') as $v)
		                      <label class="badge badge-success">{{ $v }} </label>
		                  @endforeach
		              @endif
		          </td>
		      </tr>
			</v-simple-table>
		  </div>
		  
		</div>
	  
	<div v-else>  
    <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          <v-text-field outlined required name="email" label="Email" type="email"
                          v-model="form.email" :rules="emailRules">
            </v-text-field>
          <v-switch v-model="form.is_active" label="Active?"></v-switch>
            <v-select v-if="is_admin"
                outlined
                required
                :items="institutions"
                v-model="form.inst_id"
                value="user.inst_id"
                label="Institution"
                item-text="name"
                item-value="id"
            ></v-select>
            <v-text-field v-else outlined readonly label="Institution" :value="inst_name"></v-text-field>
            <input type="hidden" id="inst_id" name="inst_id" :value="user.inst_id">
            <v-text-field outlined name="password" label="Password" id="password" type="password"
                          v-model="form.password" :rules="passwordRules">
            </v-text-field>
            <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                          type="password" v-model="form.confirm_pass" :rules="passwordRules">
            </v-text-field>
			<div class="field-wrapper">
	            <v-subheader v-text="'User Roles'"></v-subheader>
	            <v-select v-if="is_manager || is_admin"
	                :items="roles"
	                v-model="form.roles"
	                :value="user.roles"
	                item-text="name"
	                item-value="id"
	                label="User Role(s)"
	                multiple
	                chips
	                hint="Define roles for user"
	                persistent-hint
	            ></v-select>
			</div>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save User Settings
            </v-btn>
			<v-btn small type="button" @click="hideForm">cancel</v-btn>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </form>
	</div>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                user: { type:Object, default: () => {} },
                roles: { type:Array, default: () => [] },
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
                var self = this;
                if (self.form.password!=self.form.confirm_pass) {
                    self.failure = 'Passwords do not match! Please re-enter';
                }
                this.form.patch('/users/'+self.user['id'])
                    .then( function(response) {
                        if (response.result) {
                            self.success = response.msg;
                        } else {
                            self.failure = response.msg;
                        }
                    });
					self.showForm = false;
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
	            var self = this;
	            self.showForm = true;
			},
	        hideForm (event) {
	            var self = this;
	            self.showForm = false;
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
