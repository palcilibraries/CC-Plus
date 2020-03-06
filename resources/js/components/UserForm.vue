<template>
  <div>
    <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)">
      <v-container grid-list-md>
        <v-row>
          <v-col class="d-flex" cols="12" sm="6">
            <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col class="d-flex" cols="12" sm="6">
            <!-- <v-text-field v-model="form.email" label="Email" outlined></v-text-field> -->
            <v-text-field outlined required name="email" label="Email" type="email"
                          v-model="form.email" :rules="emailRules">
            </v-text-field>
          </v-col>
        </v-row>
        <v-row v-if="manager || admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-switch v-model="form.is_active" label="Active?"></v-switch>
          </v-col>
        </v-row>
        <v-row v-if="admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-select
                outlined
                required
                :items="institutions"
                v-model="form.inst_id"
                value="user.inst_id"
                label="Institution"
                item-text="name"
                item-value="id"
            ></v-select>
          </v-col>
        </v-row>
        <v-row v-else>
          <v-col class="d-flex" cols="12" sm="6">
            <v-text-field outlined readonly label="Institution" :value="inst_name"></v-text-field>
            <input type="hidden" id="inst_id" name="inst_id" :value="user.inst_id">
          </v-col>
        </v-row>
        <v-row>
          <v-col class="d-flex" cols="12" sm="6">
            <!-- <v-text-field v-model="form.password" label="Password" outlined></v-text-field> -->
            <v-text-field outlined name="password" label="Password" id="password" type="password"
                          v-model="form.password" :rules="passwordRules">
            </v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col class="d-flex" cols="12" sm="6">
            <!-- <v-text-field v-model="form.confirm_pass" label="Confirm Password" outlined></v-text-field> -->
            <v-text-field outlined name="confirm_pass" label="Confirm Password" id="confirm_pass"
                          type="password" v-model="form.confirm_pass" :rules="passwordRules">
            </v-text-field>
          </v-col>
        </v-row>
        <v-row v-if="manager || admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-subheader v-text="'User Roles'"></v-subheader>
            <v-select
                :items="roles"
                v-model="form.roles"
                value="user_roles"
                item-text="name"
                item-value="id"
                label="User Role(s)"
                multiple
                chips
                hint="Define roles for user"
                persistent-hint
            ></v-select>
          </v-col>
        </v-row>
        <v-row align="center">
          <v-flex md3>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save User Settings
            </v-btn>
          </v-flex>
        </v-row>
      </v-container>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </form>
  </div>
</template>

<script>
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                user: { type:Object, default: () => {} },
                roles: { type:Array, default: () => [] },
                user_roles: { type:Array, default: () => [] },
                institutions: { type:Array, default: () => [] },
                manager: { type:Number, default:0 },
                admin: { type:Number, default:0 },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
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
                    roles: this.user_roles
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
            },
        },
        mounted() {
            if (!this.admin) {
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
