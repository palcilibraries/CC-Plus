<template>
  <div>
    <div class="d-flex pl-2">
        <h3 v-if="is_admin">Institution Settings ({{ mutable_inst.name }})</h3>
        <h3 v-else>My Institution ({{ mutable_inst.name }})</h3>
        <!-- <v-icon v-if="!showInstForm" title="Edit Institution Settings" @click="showInstForm=true">mdi-cog-outline</v-icon> -->
        <v-icon v-if="!showInstForm" title="Edit Institution Settings" @click="instDialog=true">mdi-cog-outline</v-icon>
        &nbsp;
        <v-icon v-if="is_admin && mutable_inst.can_delete" title="Delete Institution" @click="destroy(mutable_inst.id)">
          mdi-trash-can-outline
        </v-icon>
    </div>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Users -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h3>Users</h3>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <user-data-table :institutions="mutable_institutions" :allowed_roles="all_roles" :all_groups="mutable_groups"
          ></user-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Harvest Log summary table -->
      <v-expansion-panel>
    	  <v-expansion-panel-header>
          <h3>Recent Harvest Activity</h3>
    	  </v-expansion-panel-header>
    	  <v-expansion-panel-content>
          <harvestlog-summary-table :harvests='harvests' :inst_id="institution.id"></harvestlog-summary-table>
    	  </v-expansion-panel-content>
  	  </v-expansion-panel>
      <!-- Sushi Settings -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h3>Sushi Connections</h3>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <sushisettings-data-table :institutions="mutable_institutions" :inst_groups="mutable_groups"
                                    :unset_conso="mutable_unset_con" :filters="sushi_filters"
          ></sushisettings-data-table>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
    </v-expansion-panels>
    <v-dialog v-model="instDialog" persistent content-class="ccplus-dialog">
      <institution-dialog dtype="edit" :institution="mutable_inst" :groups="all_groups" @inst-complete="instDialogDone"
                          :key="idKey"
      ></institution-dialog>
    </v-dialog>
    <v-dialog v-model="importDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Sushi Settings imports function exclusively as Updates. No existing settings
              will be deleted.</strong>
            </p>
            <p>
              Imports will overwrite existing settings whenever a match for an Institution-ID and Provider-ID are
              found in the import file. If no setting exists for a given valid provider-institution pair, a new
              setting will be created and saved. Any values in columns D-H which are NULL, blank, or missing for
              a valid provider-institution pair, will result in the Default value being stored for that field.
            </p>
            <p>
              Generating an export of the existing settings FIRST will provide detailed instructions for
              importing on the "How to Import" tab and will help ensure that the desired end-state is achieved.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-col class="d-flex">
            <v-btn small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {institution: { type:Object, default: () => {} },
                users: { type:Array, default: () => [] },
                unset_conso: { type:Array, default: () => [] },
                unset_global: { type:Array, default: () => [] },
                all_connectors: { type:Object, default: () => {} },
                all_groups: { type:Array, default: () => [] },
                all_roles: { type:Array, default: () => [] },
                harvests: { type:Array, default: () => [] }
        },
        data() {
            return {
                success: '',
                failure: '',
                user_failure: '',
                form_key: 1,
                status: '',
                panels: [],
                statusvals: ['Inactive','Active'],
                showInstForm: false,
                importDialog: false,
                idKey: 0,
                instDialog: false,
                mutable_inst: { ...this.institution },
                mutable_institutions: [this.institution],
                mutable_unset_con: [ ...this.unset_conso ],
                mutable_unset_glo: [ ...this.unset_global ],
                mutable_users: [ ...this.users ],
                mutable_groups: [ ...this.institution.groups],
                mutable_connectors: { ...this.all_connectors },
                sushi_filters: {inst: [], group: 0, prov: [], harv_stat: []},
                instForm: new window.Form({
                    name: this.institution.name,
                    local_id: this.institution.local_id,
                    is_active: this.institution.is_active,
                    fte: this.institution.fte,
                    institution_groups: this.institution.groups,
                    notes: this.institution.notes,
                }),
                new_provider: { 'connectors': [] },
                current_user: {},
                pw_show: false,
                pwc_show: false,
                dialogType: 'create',
                months: ['January','February','March','April','May','June','July','August','September','October','November',
                         'December'],
                userHeaders: [
                  { text: 'Name ', value: 'name' },
                  { text: 'Permission Level', value: '' },
                  { text: 'Last Login', value: 'last_login' },
                  { text: '', value: ''}
                ],
                emailRules: [
                    v => !!v || 'E-mail is required',
                    v => ( /.+@.+/.test(v) || v=='Administrator') || 'E-mail must be valid'
                ],
                sushiForm: new window.Form({
                    inst_id: this.institution.id,
                    prov_id: null,
                    global_id: null,
                    customer_id: '',
                    requestor_id: '',
                    API_key: '',
                    extra_args: '',
                    status: 'Enabled'
                }),
                dtKey: 1,
                csv_upload: null,
                export_filters: { 'inst': [this.institution.id], 'prov': [], 'group': 0 },
                import_type: '',
                import_types: ['Add or Update', 'Full Replacement']
            }
        },
        methods: {
            instFormSubmit (event) {
                this.success = '';
                this.failure = '';
                this.instForm.patch('/institutions/'+this.institution['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_inst = response.institution;
                            this.status = this.statusvals[this.instForm.is_active];
                            this.mutable_groups = this.instForm.institution_groups;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.showInstForm = false;
                this.dtKey += 1;
            },
            onUnsetChange (type) {
                let _prov = {};
                if (type == 'conso') {
                  _prov = this.mutable_unset_con.find(p => p.id == this.sushiForm.prov_id);
                } else if (type == 'global') {
                  _prov = this.mutable_unset_glo.find(p => p.id == this.sushiForm.global_id);
                } else {
                  this.failure = 'Javascript error : unknown type in onUnsetChange';
                  return;
                }
                this.new_provider = { ..._prov };
                this.sushiForm.customer_id = '';
                this.sushiForm.requestor_id = '';
                this.sushiForm.API_key = '';
                this.sushiForm.extra_args = '';
                this.failure = '';
                this.success = '';
                this.testData = '';
                this.testStatus = '';
            },
            enableImportDialog () {
                this.csv_upload = null;
                this.importDialog = true;
            },
            importSubmit (event) {
                this.success = '';
                this.failure = '';
                if (this.import_type == '') {
                    this.failure = 'An import type is required';
                    return;
                }
                if (this.csv_upload==null) {
                    this.failure = 'A CSV import file is required';
                    return;
                }
                let formData = new FormData();
                formData.append('csvfile', this.csv_upload);
                formData.append('type', this.import_type);
                axios.post('/sushisettings/import', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                      })
                     .then( (response) => {
                         if (response.data.result) {
                             this.success = response.data.msg;
                         } else {
                             this.failure = response.data.msg;
                         }
                     });
                this.importDialog = false;
            },
            doExport () {
                let url = "/sushi-export?filters="+JSON.stringify(this.export_filters);
                window.location.assign(url);
            },
            destroy (instid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting an institution cannot be reversed, only manually recreated."+
                        " Because this institution has no harvested usage data, it can be safely"+
                        " deleted. NOTE: All users and SUSHI settings connected to this institution"+
                        " will also be removed.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/institutions/'+instid)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/institutions");
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
            instDialogDone ({ result, msg, inst }) {
                this.success = '';
                this.failure = '';
                if (result == 'Success') {
                    this.success = msg;
                    this.mutable_inst = { ...inst };
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
          ...mapGetters(['is_admin']),
        },
        mounted() {
            this.status=this.statusvals[this.institution.is_active];
            this.sushi_filters.inst = [this.institution.id];
            console.log('Institution Component mounted.');
        }
    }
</script>
<style scoped>
.Enabled { color: #00dd00; }
.Disabled { color: #dd0000; }
.Suspended {
  color: #999999;
  font-style: italic;
}
.Incomplete {
  color: #dd0000;
  font-style: italic;
}
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
</style>
