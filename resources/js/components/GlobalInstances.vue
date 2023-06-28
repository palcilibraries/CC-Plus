<template>
  <div>
    <v-row class="d-flex mt-2 justify-center" no-gutters>
      <v-btn small color="primary" @click="createForm">Add Consortium Instance</v-btn>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table :headers="con_headers" :items="mutable_consortia" item-key="id" disable-sort
                  :hide-default-footer="hide_user_footer" :key="'CDT'+dtKey">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.ccp_key }}</td>
          <td>{{ item.name }}</td>
          <td><a target="_blank" :href="'mailto:'+item.email">{{ item.email }}</a></td>
          <td>
            <v-icon v-if="!consoDialog" title="Edit Instance Settings" @click="editForm(item.id)">mdi-cog-outline</v-icon>
            &nbsp;
            <v-icon title="Delete Instance" @click="destroy(item.id)">mdi-trash-can-outline</v-icon>
          </td>
        </tr>
      </template>
    </v-data-table>
    <v-dialog v-model="consoDialog" content-class="ccplus-dialog">
        <v-container grid-list-sm>
          <v-form v-model="formValid">
            <v-row class="d-flex ma-0">
              <v-col class="d-flex pt-2 justify-center">
                <h3 v-if="dialogType=='edit'" align="center">Edit Consortium settings</h3>
                <h3 v-else align="center">Create new consortium instance</h3>
              </v-col>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.name" :value="current_consortium.name" label="Consortium name" outlined dense required
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.ccp_key" label="Database Prefix Key" outlined dense :readonly="dialogType=='edit'"
                            :rules="[rules.required]"  hint="Cannot be modified once created!"
              ></v-text-field>
            </v-row>
            <v-row v-if="dialogType=='create' && form.ccp_key.length>0" class="d-flex mx-2 mb-2 warning-message">
              Once created, the database key cannot be changed from within the CC-Plus application.
              Changes are possible at the operating system level only.
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.admin_user" label="Username" readonly outlined dense></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field id="admin_pass" name="admin_pass" label="Administrator Password" outlined dense
                            :type="pw_show ? 'text' : 'password'" :append-icon="pw_show ? 'mdi-eye-off' : 'mdi-eye'"
                            @click:append="pw_show = !pw_show" v-model="form.admin_pass" :rules="passwordRules"
                            :required="dialogType=='create'"
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field id="admin_confirm_pass" name="admin_confirm_pass" label="Confirm password" outlined dense
                            :type="pwc_show ? 'text' : 'password'" :append-icon="pwc_show ? 'mdi-eye-off' : 'mdi-eye'"
                            @click:append="pwc_show = !pwc_show" v-model="form.admin_confirm_pass" :rules="passwordRules"
                            :required="dialogType=='create'"
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2">
              <v-text-field v-model="form.email" :value="current_consortium.email" label="Email of Consortium Administrator"
                            outlined dense clearable
              ></v-text-field>
            </v-row>
            <v-row class="d-flex mx-2 align-center">
              <v-col class="d-flex px-2" cols="4">
                <v-switch v-model="form.is_active" :value="current_consortium.is_active" label="Active?" dense></v-switch>
              </v-col>
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" @click="formSubmit" :disabled="!formValid">Save Consortium</v-btn>
              </v-col>
              <v-col class="d-flex px-2" cols="4">
                <v-btn x-small color="primary" type="button" @click="consoDialog=false">Cancel</v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-container>
    </v-dialog>
  </div>
</template>
<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
      consortia: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        dialogError: '',
        mutable_consortia: [...this.consortia],
        current_consortium: {},
        con_headers: [
            { text: 'Database Key', value: 'ccp_key' },
            { text: 'Name', value: 'name' },
            { text: 'Email', value: 'email' },
            { text: '', value: 'data-table-expand' },
        ],
        dtKey: 1,
        hide_user_footer: true,
        hide_counter_footer: true,
        consoDialog: false,
        dialogType: "create",
        formValid: true,
        pw_show: false,
        pwc_show: false,
        form: new window.Form({
            ccp_key: '',
            name: '',
            email: '',
            is_active: 1,
            admin_user: 'Administrator',
            admin_pass: '',
            admin_confirm_pass: '',
        }),
        rules: {
          required: value => !!value || 'Field is required',
        },
      }
    },
    methods: {
      formSubmit (event) {
          this.success = '';
          this.failure = '';
          this.dialogError = '';
          if (this.dialogType == 'edit') {
              this.form.patch('/consortia/'+this.current_consortium.id)
                  .then((response) => {
                      if (response.result) {
                          // Update mutable_consortia record with newly saved values...
                          var idx = this.mutable_consortia.findIndex(u => u.id == this.current_consortium.id);
                          Object.assign(this.mutable_consortia[idx], response.consortium);
                          this.success = response.msg;
                          this.dtKey++;
                      } else {
                          this.failure = response.msg;
                      }
                  });
          } else if (this.dialogType == 'create') {
            if (this.form.admin_pass != this.form.admin_confirm_pass) {
                this.dialogError = 'Passwords do not match! Please re-enter';
                return;
            }
            if (this.form.ccp_key == '' || this.form.ccp_key == null) {
                this.dialogError = 'Database Key is Required';
                return;
            }
            this.form.post('/consortia')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new consortium onto the mutable array and re-sort it
                        this.mutable_consortia.push(response.consortium);
                        this.mutable_consortia.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                        this.dtKey++;
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
          }
          this.consoDialog = false;
      },
      destroy (conId) {
          Swal.fire({
            title: 'Are you sure?',
            text: "Deleting a Consortium cannot be reversed, only manually recreated."+
                  "This operation will NOT remove database tables or harvested data."+
                  "These tasks will need to be handled at the operating system level.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, proceed'
          }).then((result) => {
            if (result.value) {
                axios.delete('/consortia/'+conId)
                     .then( (response) => {
                         if (response.data.result) {
                             this.success = response.data.msg;
                             this.failure = '';
                             this.mutable_consortia.splice(this.mutable_consortia.findIndex(c=> c.id == conId),1);
                             this.dtKey++;
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
      // Edit Consortium DOES NOT MODIFY the consortium admin account(s). That is done via the Users routes
      // so... the form.admin_*  fields are ignored by the consortium update() method
      editForm (consoId) {
          this.failure = '';
          this.success = '';
          this.dialogError = '';
          this.dialogType = "edit";
          this.current_consortium = this.mutable_consortia[this.mutable_consortia.findIndex(c=> c.id == consoId)];
          this.form.name = this.current_consortium.name;
          this.form.is_active = this.current_consortium.is_active;
          this.form.email = this.current_consortium.email;
          this.form.admin_user = 'Administrator';
          this.form.admin_pass = '';
          this.form.admin_confirm_pass = '';
          this.consoDialog = true;
      },
      // Create Consortium Dsets the initial consortium admin account.
      // so... the form.admin_*  fields matter for the consortium store() method
      createForm () {
          this.failure = '';
          this.success = '';
          this.dialogError = '';
          this.dialogType = "create";
          this.current_consortium = {ccp_key: '', name: '', email: '', is_active: 1};
          this.form.ccp_key = '';
          this.form.name = '';
          this.form.email = '';
          this.form.is_active = 1;
          this.form.admin_user = 'Administrator';
          this.form.admin_pass = '';
          this.form.admin_confirm_pass = '';
          this.consoDialog = true;
      },
    },
    computed: {
      passwordRules() {
          if (this.dialogType == 'create') {
              return [ v => !!v || 'Password is required',
                       v => v.length >= 8 || 'Password must be at least 8 characters'
                     ];
          } else {
              return [];
          }
      }
    },
    mounted() {
      console.log('GlobalAdmin Dashboard mounted.');
    }
  }
</script>
