<template>
  <div>
    <v-card>
      <v-container grid-list-md>
        <v-form v-model="formValid" :key="'UFrm'+form_key">
          <v-row class="d-flex ma-2" no-gutters>
            <v-col v-if="dtype=='edit'" class="d-flex pt-4 justify-center"><h4 align="center">Edit User Settings</h4></v-col>
            <v-col v-else class="d-flex pt-4 justify-center"><h4 align="center">Create a User</h4></v-col>
          </v-row>
          <v-row class="d-flex mx-2" no-gutters>
            <v-text-field name="name" label="Name" v-model="form.name" outlined dense></v-text-field>
          </v-row>
          <v-row class="d-flex mx-2" no-gutters>
            <v-text-field name="email" label="Email" v-model="form.email" type="email" outlined dense
                          :rules="emailRules"
            ></v-text-field>
          </v-row>
          <v-row class="d-flex mx-2 align-center" no-gutters>
            <v-col class="d-flex justify-center" cols="6">
              <v-switch name="is_active" label="Active?" v-model="form.is_active" dense></v-switch>
            </v-col>
            <v-col v-if="mutable_institutions.length>1" class="d-flex justify-center" cols="6">
              <v-btn small color="primary" @click="instDialog=true">Create an Institution</v-btn>
            </v-col>
          </v-row>
          <v-row v-if="is_admin" class="d-flex mx-2" no-gutters>
            <v-select :items="mutable_institutions" v-model="form.inst_id" label="Institution" item-value="id" item-text="name"
                      @change="changeInst" :rules="[(v) => !!v || 'Institution assignment is required']" outlined dense
            ></v-select>
          </v-row>
          <v-row v-else class="d-flex mx-2" no-gutters>
            <v-text-field label="Institution" :value="inst_name" outlined dense readonly></v-text-field>
          </v-row>
          <v-row class="d-flex mx-2" no-gutters>
            <v-text-field name="password" label="Reset Password" v-model="form.password" :type="pw_show ? 'text' : 'password'"
                          :rules="passwordRules" :required="dtype=='create'" @click:append="pw_show = !pw_show"
                          :append-icon="pw_show ? 'mdi-eye-off' : 'mdi-eye'" outlined dense>
            </v-text-field>
          </v-row>
          <v-row class="d-flex mx-2" no-gutters>
            <v-text-field name="confirm_pass" label="Reset Password Confirmation" v-model="form.confirm_pass"
                          :type="pwc_show ? 'text' : 'password'" :rules="passwordRules" :required="dtype=='create'"
                          @click:append="pwc_show = !pwc_show" :append-icon="pwc_show ? 'mdi-eye-off' : 'mdi-eye'" outlined dense
            ></v-text-field>
          </v-row>
          <v-row v-if="is_manager || is_admin" class="d-flex mx-2" no-gutters>
            <div class="field-wrapper">
              <v-subheader v-text="'User Roles'"></v-subheader>
              <v-select :items="allowed_roles" v-model="form.roles" label="User Role(s)" :value="mutable_user.roles"
                        item-text="name" item-value="id" multiple chips hint="Define roles for user" persistent-hint dense
                        :required="dtype=='create'"
              ></v-select>
              <div style="display: inline-block;">
                Roles<br>
                Admin: can create and manage settings for all users, institutions, and providers<br>
                Local Admin: can manage settings for their own institutions and can create and manage users within their institution<br>
                User: can view statistics for their own institution<br>
                Viewer: can view statistics for all institutions
              </div>
            </div>
          </v-row>
        </v-form>
      </v-container>
      <div v-if="success || failure" class="status-message">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-card-actions>
        <v-col class="d-flex">
          <v-btn class='btn' x-small color="primary" @click="saveUser" :disabled="!formValid">Save User</v-btn>
        </v-col>
        <v-col class="d-flex">
          <v-btn class='btn' x-small type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
        </v-col>
      </v-card-actions>
    </v-card>
    <v-dialog v-model="instDialog" persistent content-class="ccplus-dialog">
      <institution-dialog dtype="create" :groups="groups" @inst-complete="instDialogDone" :key="idKey"></institution-dialog>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String, default: "create" },
            user: { type:Object, default: () => {} },
            allowed_roles: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        success: '',
        failure: '',
        form_key: 1,
        idKey: 0,
        formValid: true,
        pw_show: false,
        pwc_show: false,
        inst_name: '',
        instDialog: false,
        mutable_user: { ...this.user },
        mutable_institutions: [ ...this.institutions ],
        added_insts: [],
        form: new window.Form({
            name: '',
            inst_id: null,
            is_active: 1,
            email: '',
            password: '',
            confirm_pass: '',
            roles: []
        }),
        emailRules: [
          v => !!v || 'E-mail is required',
          v => ( /.+@.+/.test(v) || v=='Administrator') || 'E-mail must be valid'
        ],
      }
    },
    methods: {
      saveUser (event) {
          this.success = '';
          this.failure = '';
          if (this.form.password != this.form.confirm_pass) {
              this.failure = 'Passwords do not match! Please re-enter';
              return;
          }
          if (this.dtype == 'edit') {
              if  (this.form.password.length>0 && this.form.password.length<8) {
                  this.failure = 'Password must be at least 8 characters';
                  return;
              }
              this.form.patch('/users/'+this.user.id)
                  .then((response) => {
                      var _user   = (response.result) ? response.user : null;
                      var _result = (response.result) ? 'Success' : 'Fail';
                      this.$emit('user-complete', { result:_result, msg:response.msg, user:_user, new_inst:this.added_insts });
                  });
          } else if (this.dtype == 'create') {
              this.form.post('/users')
                  .then((response) => {
                      var _user   = (response.result) ? response.user : null;
                      var _result = (response.result) ? 'Success' : 'Fail';
                      this.$emit('user-complete', { result:_result, msg:response.msg, user:_user, new_inst:this.added_insts });
                  });
          }
      },
      cancelDialog () {
        this.success = '';
        this.failure = '';
        this.$emit('user-complete', { result:'Cancel', msg:null, inst:null, new_inst:this.added_insts });
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
              if (!this.user.roles.includes(view_role.id) && this.form.roles.includes(view_role.id)) {
                  this.form.roles.splice(this.form.roles.indexOf(view_role.id), 1);
              }
          }
      },
      instDialogDone ({ result, msg, inst }) {
          this.success = '';
          this.failure = '';
          if (result == 'Success') {
              this.success = msg;
              // Add the new institution onto the mutable array and re-sort it
              this.mutable_institutions.push(inst);
              this.mutable_institutions.sort((a,b) => {
                if ( a.name < b.name ) return -1;
                if ( a.name > b.name ) return 1;
                return 0;
              });
              // apply new new institution ID to the user form; don't change if already set
              if (this.form.inst_id == null) {
                  this.form.inst_id = inst.id;
                  this.form_key += 1;
              }
              // Add inst to the array we'll emit to caller
              this.added_insts.push(inst);
          } else if (result == 'Fail') {
              this.failure = msg;
          } else if (result != 'Cancel') {
              this.failure = 'Unexpected Result returned from dialog - programming error!';
          }
          this.instDialog = false;
          this.idKey += 1;
      },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin']),
      passwordRules() {
          if (this.dtype == 'create') {
              return [ v => !!v || 'Password is required',
                       v => v.length >= 8 || 'Password must be at least 8 characters'
                     ];
          // saveUser handles password validation for edit
          } else {
              return [];
          }
      }
    },
    mounted() {
      this.form.name = this.user.name;
      this.form.inst_id = (this.is_admin) ? 1 : this.institutions[0].id;
      this.inst_name = (this.is_admin) ? null : this.institutions[0].name;
      this.form.is_active = this.user.is_active;
      this.form.email = this.user.email;
      this.form.roles = this.user.roles;
      console.log('UserDialog Component mounted.');
    }
  }
</script>
<style>
</style>
