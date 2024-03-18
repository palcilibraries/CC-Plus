<template>
  <div>
    <v-row class="d-flex pl-2" no-gutters>
      <v-col class="d-flex px-2 flex-shrink-1">
        <h1>{{ mutable_inst.name }}</h1>
        &nbsp;
        <div class="idbox">
          <v-icon title="CC+ Institution ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ mutable_inst.id }}
        </div>
        &nbsp;
        <v-icon v-if="!showInstForm" title="Edit Institution Settings" @click="instDialog=true">mdi-cog-outline</v-icon>
        &nbsp;
        <v-icon v-if="is_admin && mutable_inst.can_delete" title="Delete Institution" @click="destroy(mutable_inst.id)">
          mdi-trash-can-outline
        </v-icon>
      </v-col>
    </v-row>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Users -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Users</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <user-data-table :institutions="mutable_institutions" :allowed_roles="all_roles" :all_groups="mutable_groups"
          ></user-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Providers -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Providers</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <provider-data-table :key="provKey" :providers="mutable_providers" :institutions="mutable_institutions"
                               :master_reports="master_reports" @connect-prov="connectProv" @disconnect-prov="disconnectProv"
                               @change-prov="updateProv" :inst_context="this.institution.id"
          ></provider-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Sushi Settings -->
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>Sushi Settings</h2>
  	    </v-expansion-panel-header>
  	    <v-expansion-panel-content>
          <sushisettings-data-table :key="sushiKey" :providers="mutable_providers" :institutions="mutable_institutions"
                                    :inst_groups="mutable_groups" :unset="mutable_unset"
                                    :inst_context="this.institution.id"
          ></sushisettings-data-table>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
      <!-- Harvest Log summary table -->
      <v-expansion-panel>
    	  <v-expansion-panel-header>
          <h2>Recent Harvest Activity</h2>
    	  </v-expansion-panel-header>
    	  <v-expansion-panel-content>
          <harvestlog-summary-table :harvests='harvests' :inst_id="institution.id"></harvestlog-summary-table>
    	  </v-expansion-panel-content>
  	  </v-expansion-panel>
    </v-expansion-panels>
    <v-dialog v-model="instDialog" content-class="ccplus-dialog">
      <institution-dialog dtype="edit" :institution="mutable_inst" :groups="all_groups" @inst-complete="instDialogDone"
                          :key="idKey"
      ></institution-dialog>
    </v-dialog>
    <v-dialog v-model="importDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Sushi Settings</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
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
                all_providers: { type:Array, default: () => [] },
                all_groups: { type:Array, default: () => [] },
                all_roles: { type:Array, default: () => [] },
                harvests: { type:Array, default: () => [] },
                unset_global: { type:Array, default: () => [] },
                master_reports: { type:Array, default: () => [] },
        },
        data() {
            return {
                success: '',
                failure: '',
                provKey: 1,
                sushiKey: 1,
                status: '',
                panels: [],
                statusvals: ['Inactive','Active'],
                showInstForm: false,
                importDialog: false,
                idKey: 0,
                instDialog: false,
                mutable_inst: { ...this.institution },
                mutable_institutions: [this.institution],
                mutable_providers: [ ...this.all_providers],
                mutable_unset: [...this.unset_global],
                mutable_users: [ ...this.users ],
                mutable_groups: [ ...this.institution.groups],
                instForm: new window.Form({
                    name: this.institution.name,
                    local_id: this.institution.local_id,
                    is_active: this.institution.is_active,
                    fte: this.institution.fte,
                    institution_groups: this.institution.groups,
                    notes: this.institution.notes,
                }),
                sushiForm: new window.Form({
                    inst_id: this.institution.id,
                    prov_id: null,
                    global_id: null,
                    customer_id: '',
                    requestor_id: '',
                    api_key: '',
                    extra_args: '',
                    status: 'Enabled'
                }),
                dtKey: 1,
                csv_upload: null,
                import_type: '',
                import_types: ['Add or Update', 'Full Replacement']
            }
        },
        watch: {
          current_panels: {
             handler () {
                 this.$store.dispatch('updatePanels',this.panels);
             },
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
            updateProv (prov) {
              var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
              this.mutable_providers.splice(idx,1,prov);
              this.sushiKey += 1;
            },
            connectProv (prov) {
              var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
              this.mutable_providers.splice(idx,1,prov);
              this.mutable_unset.splice(this.mutable_unset.findIndex(p => p.id==prov.global_id),1);
              this.sushiKey += 1;
            },
            disconnectProv (prov) {
              var idx = this.mutable_providers.findIndex(p => p.id == prov.id);
              this.mutable_providers.splice(idx,1,prov);
              let global_data = prov.global_prov;
              this.mutable_unset.push(global_data);
              this.mutable_unset.sort((a,b) => {
                if ( a.name < b.name ) return -1;
                if ( a.name > b.name ) return 1;
                return 0;
              });
              this.sushiKey += 1;
            },
        },
        computed: {
          ...mapGetters(['is_admin', 'panel_data']),
          current_panels() { return this.panels; }
        },
        beforeCreate() {
          // Load existing store data
          this.$store.commit('initialiseStore');
      	},
        beforeMount() {
          // Set page name in the store
          this.$store.dispatch('updateDashboard','showinstitution');
      	},
        mounted() {
            this.status=this.statusvals[this.institution.is_active];

            // Set datatable options with store-values
            Object.assign(this.panels, this.panel_data);

            // Subscribe to store updates
            this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
            console.log('Show Institution Component mounted.');
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
