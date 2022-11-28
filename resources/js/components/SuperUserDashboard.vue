<template>
  <div>
    <v-row class="d-flex ma-0" no-gutters>
      <v-col class="d-flex pa-0" cols="4">
        <h3>Defined Instances</h3>
      </v-col>
      <v-col class="d-flex px-2" cols="2">
        <v-btn small color="primary" @click="createForm">Create new consortium instance</v-btn>
      </v-col>
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
    <v-dialog v-model="consoDialog" persistent max-width="800px">
      <v-card>
        <v-card-title>
          <span v-if="dialogType=='edit'">Edit Consortium settings</span>
          <span v-else>Creating a new consortium instance</span>
        </v-card-title>
        <v-container>
          <v-card-text>
            <v-form class="in-page-form">
              <div v-if="dialogType=='create'" class="field-wrapper">
                <p>
                  <strong>Note:</strong><br />The Database prefix key is used to define a new database. Once
                  created, this key cannot be modified by the CC+ application. If you <em><strong>really,
                  really</strong></em> need to change this later, it has to be done at the Operating System level.
                </p>
                <v-row class="d-flex ma-0" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">Database Prefix Key</v-col>
                  <v-col class="d-flex px-2">
                    <v-text-field v-model="form.ccp_key" label="ccp_key" outlined></v-text-field>
                  </v-col>
                </v-row>
                <v-row class="d-flex ma-0" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">Admin Username/Email</v-col>
                  <v-col class="d-flex px-2">
                    <v-text-field v-model="form.admin_user" label="username" outlined></v-text-field>
                  </v-col>
                </v-row>
                <v-row class="d-flex ma-0" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">Admin Password</v-col>
                  <v-col class="d-flex px-2">
                    <v-text-field v-model="form.admin_pass" label="admin_pass" type="password" outlined
                                   :rules="passwordRules"
                    ></v-text-field>
                  </v-col>
                </v-row>
                <v-row class="d-flex ma-0" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">Admin Password Confirm</v-col>
                  <v-col class="d-flex px-2">
                    <v-text-field v-model="form.admin_confirm_pass" label="admin_confirm_pass" type="password" outlined
                                  :rules="passwordRules"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </div>
              <div v-else>
                <v-row class="d-flex my-2" no-gutters>
                  <v-col class="d-flex pa-0" cols="3">Database Key</v-col>
                  <v-col class="d-flex px-2"><strong>{{ current_consortium.ccp_key }}</strong></v-col>
                </v-row>
              </div>
              <v-row class="d-flex ma-0" no-gutters>
                <v-col class="d-flex pa-0" cols="3">Consortium Name</v-col>
                <v-col class="d-flex px-2">
                  <v-text-field v-model="form.name" :value="current_consortium.name" label="name" outlined></v-text-field>
                </v-col>
              </v-row>
              <v-row class="d-flex ma-0" no-gutters>
                <v-col class="d-flex pa-0" cols="3">Consortium Email</v-col>
                <v-col class="d-flex px-2">
                  <v-text-field v-model="form.email" :value="current_consortium.email" label="email" outlined></v-text-field>
                </v-col>
              </v-row>
              <v-row class="d-flex ma-0" no-gutters>
                <v-col class="d-flex pa-0" cols="3">Is Active</v-col>
                <v-col class="d-flex px-2">
                  <v-switch v-model="form.is_active" :value="current_consortium.is_active" label="Active?"></v-switch>
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn x-small color="primary" @click="formSubmit">Save Consortium</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn x-small color="primary" @click="consoDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </v-container>
      </v-card>
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
        form: new window.Form({
            ccp_key: '',
            name: '',
            email: '',
            is_active: 1,
            admin_user: '',
            admin_pass: '',
            admin_confirm_pass: '',
        }),
        emailRules: [
            v => !!v || 'E-mail is required',
            v => /.+@.+/.test(v) || 'E-mail must be valid'
        ],
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
          this.form.admin_user = '';
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
          this.form.admin_user = '';
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
      console.log('SuperUser Dashboard mounted.');
    }
  }
</script>
<style>
</style>
