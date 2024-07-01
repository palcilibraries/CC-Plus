<template>
  <div>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-row class="d-flex mt-2" no-gutters>
      <v-col class="d-flex" cols="3">&nbsp;</v-col>
      <v-col class="d-flex px-1" cols="3">
        <v-btn small color="primary" @click="providerImportForm">Import Providers</v-btn>
      </v-col>
      <v-col class="d-flex px-1" cols="3">
        <a @click="doProvExport">
          <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Providers to Excel
        </a>
      </v-col>
      <v-col class="d-flex px-2 " cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details clearable
        ></v-text-field>
      </v-col>
    </v-row>
    <v-row class="d-flex pa-1 align-center" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-select :items='bulk_actions' v-model='bulkAction' @change="processBulk()"
                  item-text="action" item-value="status" label="Bulk Actions"
                  :disabled='selectedRows.length==0'></v-select>
      </v-col>
      <v-col class="d-flex px-4 align-center" cols="2">
        <span v-if="selectedRows.length>0" class="form-fail">( Will affect {{ selectedRows.length }} rows )</span>
        <span v-else> &nbsp;</span>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="3">
        <div v-if="connect_filter!=null" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="connect_filter=null"/>&nbsp;
        </div>
        <v-select :items="connect_options" v-model="connect_filter" item-text="text" item-value="val"
                  label="Filter by Connection Status"></v-select>
      </v-col>
    </v-row>
    <v-data-table v-model="selectedRows" :headers="headers" :items="filtered_providers" show-select :options="mutable_options"
                  :search="search" @update:options="updateOptions" :footer-props="footer_props"
                  :key="'mp'+dtKey" item-key="id">
      <template v-slot:item.status="{ item }">
        <div v-if="item.is_conso">
          <span v-if="item.can_edit && item.is_active">
            <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
          </span>
          <span v-else-if="!item.can_edit && item.is_active">
            <v-icon large color="green" title="Active Consortium Provider">mdi-toggle-switch</v-icon>
          </span>
          <span v-if="item.can_edit && !item.is_active">
            <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
          </span>
          <span v-else-if="!item.can_edit && !item.is_active">
            <<v-icon large color="red" title="Inactive Consortium Provider">mdi-toggle-switch</v-icon>
          </span>
        </div>
        <div v-else-if="item.can_edit && item.inst_id!=1 && item.inst_id!=null">
          <span v-if="item.is_active">
            <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
          </span>
          <span v-else>
            <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
          </span>
        </div>
        <div v-else>
          <span v-if="item.is_active"><v-icon large color="green" title="Active Global Provider">mdi-toggle-switch</v-icon></span>
          <span v-else><v-icon large color="red" title="Inactive Global Provider">mdi-toggle-switch</v-icon></span>
        </div>
      </template>
      <template v-slot:item.name="{ item }">
        <span>
          <v-icon v-if="item.is_conso" title="Consortium Provider">mdi-account-multiple</v-icon>
          <v-icon v-else-if="item.inst_id>1" title="Institutional Provider">mdi-home-outline</v-icon>
        </span>
        <span v-if="item.is_active==0" class="isInactive">
          {{ item.name }}
        </span>
        <span v-else>{{ item.name }}</span>
      </template>
      <template v-slot:item.inst_name="{ item }">
        <span v-if="item.connected.length==1 && item.is_conso">{{ item.connected[0].inst_name }}</span>
        <span v-if="item.connected.length==1 && !item.is_conso" :class="item.connected[0].inst_stat">
          <a :href="'/institutions/'+item.connected[0].inst_id" title="View Institution in new tab" target="_blank">
            {{ item.connected[0].inst_name }}
          </a>
        </span>
        <span v-if="inst_context==1 && item.connected.length>1" :class="item.inst_stat">
          Multiple Institutions <v-icon title="Show Institutions" @click="showConnected(item.id)">mdi-open-in-app</v-icon>
        </span>
        <span v-if="inst_context!=1 && item.connected.length>1" :class="item.inst_stat">Consortium + Institution</span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn v-if="item.can_connect" icon @click="connectProvider(item.id)">
            <v-icon title="Connect Provider">mdi-connection</v-icon>
          </v-btn>
          <v-btn v-if="item.can_edit || (is_manager && item.connected.length>0)" icon @click="editProvider(item.id)">
            <v-icon title="Edit Provider">mdi-cog-outline</v-icon>
          </v-btn>
          <v-btn v-else icon><v-icon color="#c9c9c9">mdi-cog-outline</v-icon></v-btn>
          <v-btn v-if="item.can_delete" icon @click="destroy(item.id)">
            <v-icon title="Disconnect Provider">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-else icon>
            <v-icon v-if="item.last_harvest!=null" title="Provider Has Harvests" color="#c9c9c9">mdi-trash-can-outline</v-icon>
            <v-icon v-else-if="item.connection_count==0" title="Global Provider" color="#c9c9c9">mdi-trash-can-outline</v-icon>
            <v-icon v-else-if="!is_admin && item.is_conso" title="Consortium Provider" color="#c9c9c9">mdi-trash-can-outline</v-icon>
            <v-icon v-else color="#c9c9c9">mdi-trash-can-outline</v-icon>
          </v-btn>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="providerImportDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Providers</v-card-title>
        <v-spacer></v-spacer>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <v-col class="d-flex justify-center" cols="8">
              <strong>Note: &nbsp;</strong><br />
              Provider imports function exclusively as Updates. No existing provider records will be deleted.<br />
              Providers assigned to  Institution ID: 1 are, by convention, consortium-wide providers.
            </v-col>
            <p>
              The import process evaluates input rows to determine if a row defines an existing, or new, provider.
              A match for an existing provider depends on matching a Global Provider ID in column-A, and a
              valid Institutional ID in column-B. If these are not found, the record is ignored. If an existing
              provider is found for the Global-Id and Insitution-ID, that provider is updated. If no provider exists
              for the Global/Institution pair, a NEW provider entry will be created.
            </p>
            <p>
              Providers can be renamed via import by providing a Global Provider ID in column-A, the corresponding
              Institution ID in column-B and a replacement name in column-C. Be aware that the new name takes effect
              immediately, and will be associated with all harvested usage data that may have been collected using
              the OLD name (data is stored by the System ID, not the name.)
            </p>
            <p>
              For these reasons, use caution when using this import function. Generating an Provider export FIRST
              will supply detailed instructions for importing on the "How to Import" tab. Generating a new Provider
              export AFTER an import operation is a good way to confirm that all the settings are as-desired.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="providerImportSubmit" :disabled="csv_upload==null"
            >Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="providerImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="connectedDialog" content-class="ccplus-dialog">
      <h1 align="center">Institutions connected to<br />{{ cur_provider.name }}</h1>
      <hr>
      <div v-for="prov in cur_provider.connected">
        <v-row class="d-flex mx-2" no-gutters>
          <v-col class="d-flex px-2">
            {{ prov.inst_name }} &nbsp;
            <v-icon title="View institution in new tab" @click="goInst(prov.inst_id)">mdi-open-in-new</v-icon>
          </v-col>
        </v-row>
      </div>
    </v-dialog>
    <v-dialog v-model="provDialog" content-class="ccplus-dialog">
      <provider-dialog :dtype="dialog_type" :key="dialogKey" :provider="cur_provider" :institutions="dialog_institutions"
                       @prov-complete="provDialogDone"
      ></provider-dialog>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import Swal from 'sweetalert2';
  export default {
    props: {
            providers: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
            inst_context: { type: Number, default: 1 }
           },
    data () {
      return {
        success: '',
        failure: '',
        inst_name: '',
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'Provider ', value: 'name', align: 'start' },
          { text: 'Enabled Reports', value: 'reports_string' },
          { text: 'Connected To', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month', align: 'center' },
        ],
        mutable_providers: [ ...this.providers ],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        bulk_actions: [ 'Set Active', 'Set Inactive', 'Connect', 'Disconnect' ],
        connect_options: [],
        bulkAction: null,
        selectedRows: [],
        dtKey: 1,
        dialogKey: 1,
        csv_upload: null,
        dialog_type: "connect",
        dialog_institutions: [ ...this.institutions ],
        providerImportDialog: false,
        mutable_options: {},
        search: '',
        new_provider: null,
        cur_provider: {},
        provDialog: false,
        connectedDialog: false,
        connect_filter: 'Connected',
        sushi_insts: [],
        sushi_provs: [],
        empty_report_state: {'TR': {'conso_enabled':false, 'prov_enabled':false},
                             'DR': {'conso_enabled':false, 'prov_enabled':false},
                             'PR': {'conso_enabled':false, 'prov_enabled':false},
                             'IR': {'conso_enabled':false, 'prov_enabled':false}},
      }
    },
    methods:{
        providerImportForm () {
            this.csv_upload = null;
            this.provDialog = false;
            this.providerImportDialog = true;
        },
        changeStatus(Id, state) {
          var _idx = this.mutable_providers.findIndex(p => p.id == Id);
          if (_idx < 0) return;
          var provider = this.mutable_providers[_idx].connected.find(p => p.inst_id == this.inst_context);
          if (typeof(provider) == 'undefined') return;
          axios.patch('/providers/'+provider.id, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   this.mutable_providers[_idx].is_active = state;
                   this.mutable_providers[_idx].active = (state == 1) ? "Active" : "Inactive";
                   this.$emit('change-prov', this.mutable_providers[_idx]);
                 }
               })
               .catch(error => {});
        },
        updateOptions(options) {
            if (Object.keys(this.mutable_options).length === 0) return;
            Object.keys(this.mutable_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_options[key]) {
                    this.mutable_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_options);
        },
        editProvider (provId) {
            // Set cur_provider to the Id passed in
            this.cur_provider = Object.assign({},this.mutable_providers.find(p => p.id == provId));
            let cnxIdx = (this.cur_provider.connected.length==1) ? 0 :
                           this.cur_provider.connected.findIndex(p => p.inst_id == this.inst_context);
            // If cnxIdx not found, setup to edit a non-conso provider with > 1 connection
            if (cnxIdx < 0) {
                this.cur_provider['id'] = provId;
                this.cur_provider['inst_id'] = null;
                this.cur_provider['report_state'] = {...this.empty_report_state};
                this.cur_provider['allow_inst_specifc'] = true;
            } else {
                this.cur_provider['id'] = this.cur_provider.connected[cnxIdx].id;
                this.cur_provider['inst_id'] = this.cur_provider.connected[cnxIdx].inst_id;
                this.cur_provider['inst_name'] = this.cur_provider.connected[cnxIdx].inst_name;
                this.cur_provider['global_id'] = this.cur_provider.connected[cnxIdx].global_id;
                this.cur_provider['day_of_month'] = this.cur_provider.connected[cnxIdx].day_of_month;
                this.cur_provider['last_harvest'] = this.cur_provider.connected[cnxIdx].last_harvest;
                this.cur_provider['report_state'] = {...this.cur_provider.connected[cnxIdx].report_state};
            }
            this.dialog_institutions = [...this.institutions];
            this.dialog_type = "edit";
            this.provDialog = true;
            this.dialogKey += 1;
        },
        connectProvider (provId) {
            this.cur_provider = Object.assign({},this.mutable_providers.find(p => p.id == provId));
            this.cur_provider['global_id'] = provId;
            this.cur_provider['id'] = null;
            // default inst_id to current context
            let context_inst = this.institutions.find( ii => ii.id == this.inst_context );
            this.cur_provider['inst_id'] = context_inst.id;
            this.cur_provider['inst_name'] = context_inst.name;
            this.cur_provider['day_of_month'] = 15;
            this.cur_provider['last_harvest'] = null;
            // if (re-)connecting a conso provider, use the report_state from the consortium definition
            if (this.cur_provider.is_conso) {
              let consoProv = this.cur_provider.connected.find( p => p.inst_id == 1 );
              if (typeof(consoProv) != 'undefined') {
                this.cur_provider.report_state = Object.assign({},consoProv.report_state);
              }
            }
            if (typeof(this.cur_provider.report_state) == 'undefined') {
              this.cur_provider['report_state'] = {...this.empty_report_state};
            }
            this.dialog_institutions = [...this.institutions];
            // clear report_state flags - make the user turn on what they want
            Object.keys(this.cur_provider.report_state).forEach( (key) =>  {
              if (!this.cur_provider.report_state[key]['conso_enabled']) {
                  this.cur_provider.report_state[key]['prov_enabled'] = false;
              }
            });
            this.dialog_type = "connect";
            this.provDialog = true;
            this.dialogKey += 1;
        },
        provDialogDone ({ result, msg, prov }) {
            this.success = '';
            this.failure = '';
            if (result == 'Success') {
                // Find and replace the provider
                let _idx = this.mutable_providers.findIndex(p => p.id == prov.id);
                this.mutable_providers.splice(_idx,1,prov);
                this.$emit('change-prov', this.mutable_providers[_idx]);
                this.success = msg;
                this.dtKey += 1;
            } else if (result == 'Fail') {
                this.failure = msg;
            } else if (result != 'Cancel') {
                this.failure = 'Unexpected Result returned from dialog - programming error!';
            }
            this.provDialog = false;
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            var skip_count = 0;
            var bulk_count = 0;
            var Action = this.bulkAction;
            var Context = this.inst_context;
            var Rows = [...this.selectedRows];
            let msg = "Bulk processing will process each requested provider sequentially.<br><br>";
            if (Action=='Set Active') {
                msg += "Activating these providers also enable related SUSHI connections, if possible. SUSHI connection<br/>";
                msg += "status will be automatically set based on the completeness of the credentials and the active/inactive ";
                msg += "state of any connected institution(s).";
            } else if (Action=='Set Inactive') {
                msg += "Deactivating these providers will stop all future automated harvesting. Any pending or queued<br />";
                msg += "harvesting jobs will not be affected. Any related and SUSHI credentials will be set to 'Suspended'.";
            } else if (Action=='Connect') {
                msg += "Connecting these providers will add an empty set of SUSHI credentials for each row, and the<br/>";
                msg += "credential(s) will be flagged as 'Incomplete'. Until the required credentials are defined, no report<br />";
                msg += "retrieval will be performed by the CC-Plus automated harvesting system.<br />";
                msg += "Note that any providers already connected will be skipped.";
            } else if (Action=='Disconnect') {
                msg += "CAUTION!!<br />Disconnecting provider records cannot be reversed!! Providers with harvested data<br />";
                msg += "will NOT be changed.<br />";
                msg += " NOTE: ALL SUSHI definitions associated with the selected providers will also be deleted!";
            } else {
                this.failure = "Unrecognized Bulk Action in processBulk!";
                return;
            }
            Swal.fire({
              title: 'Are you sure?', html: msg, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, Proceed!'
            }).then((result) => {
              if (result.value) {
                this.success = "Working...";
                if (Action=='Disconnect') {
                  for (const provider of Rows) {
                    if ( !provider.can_delete ) {
                      skip_count+=1;
                    } else {
                      axios.delete('/providers/'+provider.id)
                      .then( (response) => {
                        if (response.data.result) {
                          var _idx = this.mutable_providers.findIndex(p=>p.id == provider,id);
                          this.mutable_providers[_idx].inst_id = null;
                          this.mutable_providers[_idx].inst_name = '';
                          this.mutable_providers[_idx].connected = [];
                          this.mutable_providers[_idx].day_of_month = '';
                          this.mutable_providers[_idx].can_edit = false;
                          this.mutable_providers[_idx].can_delete = false;
                          this.$emit('disconnect-prov', this.mutable_providers[_idx]);
                        }
                      })
                      .catch({});
                      bulk_count += 1;
                    }
                  }
                  this.success  = "Selected providers: "+bulk_count+" successfully disconnected";
                  this.success += (skip_count>0) ? " ("+skip_count+" skipped)" : "";
                  this.dtKey += 1;           // update the datatable
                } else if (Action=='Connect') {
                  for (const provider of Rows) {
                    if (provider.connected.length>0) {
                      skip_count += 1;
                    } else {
                      // Connect provider consortium-wide
                      axios.post('/providers/connect', {
                          inst_id: Context,
                          global_id: provider.id,
                          sushi_stub: 1,
                      })
                      .then( (response) => {
                          if (response.data.result) {
                              var _idx = this.mutable_providers.findIndex( p => p.id == provider.id);
                              this.mutable_providers[_idx].can_edit = response.data.provider.can_edit;
                              this.mutable_providers[_idx].can_delete = response.data.provider.can_delete;
                              this.mutable_providers[_idx].inst_id = response.data.provider.inst_id;
                              this.mutable_providers[_idx].inst_name = response.data.provider.inst_name;
                              this.mutable_providers[_idx].connected = response.data.provider.connected;
                              this.mutable_providers[_idx].day_of_month = response.data.provider.day_of_month;
                              this.$emit('connect-prov', this.mutable_providers[_idx]);
                          }
                      })
                      .catch(error => {});
                      bulk_count += 1;
                    }
                  }
                  this.success  = "Selected providers: " + bulk_count + " successfully connected";
                  this.success += (skip_count>0) ? " ("+skip_count+" skipped)" : "";
                  this.dtKey += 1;           // update the datatable
                } else {
                  let state = (Action=='Set Active') ? 1 : 0;
                  for (const provider of Rows) {
                    var _idx = this.mutable_providers.findIndex(p => p.id == provider.id);
                    if (_idx < 0) continue;
                    var _prov = this.mutable_providers[_idx].connected.find(p => p.inst_id == this.inst_context);
                    if (typeof(_prov) == 'undefined') continue;
                    if (_prov.id==null || (!this.is_admin && _prov.inst_id==1) || !_prov.global_prov.is_active) {
                      skip_count+=1;
                    } else {
                      axios.patch('/providers/'+_prov.id, { is_active: state })
                           .then( (response) => {
                             if (response.data.result) {
                               this.mutable_providers[_idx].is_active = state;
                               this.mutable_providers[_idx].active = (state == 1) ? "Active" : "Inactive";
                               this.$emit('change-prov', this.mutable_providers[_idx]);
                             }
                           })
                           .catch(error => {});
                           bulk_count += 1;
                    }
                  }
                  this.success  = "Selected providers: "+bulk_count+" successfully updated";
                  this.success += (skip_count>0) ? " ("+skip_count+" skipped)" : "";
                  this.dtKey += 1;           // update the datatable
                }
              }
            })
            .catch({});
            this.bulkAction = '';
            this.selectedRows = [];
        },
        showConnected(id) {
            this.cur_provider = this.mutable_providers.find(p => p.id == id);
            this.connectedDialog = true;
        },
        goInst(id) {
            window.open("/institutions/"+id, "_blank");
        },
        // Connect provider consortium-wide
        connectOne(id) {
          this.success = '';
          this.failure = '';
          var Context = this.inst_context;
          var provIdx = this.mutable_providers.findIndex(p => p.id == id);
          var provider = this.mutable_providers[provIdx];
          // Step #1 - connect the provider to the consortium
          axios.post('/providers/connect', {
              inst_id: Context,
              global_id: id,
          })
          .then( (response) => {
              if (response.data.result) {
                  // Update mutable providers
                  this.mutable_providers[provIdx].can_edit = response.data.provider.can_edit;
                  this.mutable_providers[provIdx].can_delete = response.data.provider.can_delete;
                  this.mutable_providers[provIdx].can_connect = response.data.provider.can_connect;
                  this.mutable_providers[provIdx].inst_id = response.data.provider.inst_id;
                  this.mutable_providers[provIdx].inst_name = response.data.provider.inst_name;
                  this.mutable_providers[provIdx].connected = response.data.provider.connected;
                  this.mutable_providers[provIdx].connectors = response.data.provider.connectors;
                  this.mutable_providers[provIdx].connection_count = response.data.provider.connection_count;
                  this.mutable_providers[provIdx].day_of_month = response.data.provider.day_of_month;
                  this.mutable_providers[provIdx].allow_inst_specific = response.data.provider.allow_inst_specific;
                  // Step-2 - If we just connected an inst-specific provider, create a new, stubbed-out connection automatically
                  if (this.inst_context > 1) {
                    var stub = {'inst_id' : this.inst_context, 'prov_id' : response.data.provider.id, };
                    response.data.provider.connectors.forEach( (cnx) => { stub[cnx.name] = '-required-'; });
                    axios.post('/sushisettings', stub)
                         .catch(error => {});
                  }
                  // notify about the new provider and update the U/I
                  this.$emit('connect-prov', this.mutable_providers[provIdx]);
                  this.success = provider.name + " successfully connected";
                  this.dtKey += 1;
              }
          })
          .catch(error => {});
        },
        destroy (provid) {
            this.success = '';
            this.failure = '';
            var provIdx  = this.mutable_providers.findIndex(p => p.id == provid);
            var _prov = Object.assign({}, this.mutable_providers[provIdx]);
            // Find the record in "connected" that matches the provider for the current context
            let cnxIdx = -1;
            if (typeof(_prov) != 'undefined') {
              cnxIdx = _prov.connected.findIndex( p => p.inst_id == this.inst_context );
            }
            if (cnxIdx<0) {
              if (this.is_admin && _prov.connected.length==1) {
                 cnxIdx = 0;
              } else {
                this.failure = 'Error accessing provider data, a full page refresh is worth trying. May be a code problem.';
                return;
              }
            }
            // set provider to the target connected provider
            var provider = Object.assign({},_prov.connected[cnxIdx]);
            // setup the popup
            let notice = "Disconnecting a provider cannot be reversed, only manually reconnected."+
                         " Because this provider has no harvested usage data, it can be removed.";
            let _notes = "<br /><font color='red'><strong>NOTE:</strong>";
            let _title = "Are you sure?";
            if ( this.is_admin && provider.inst_id==1 ) {
              _notes += "<br /><strong>This is a consortium-wide provider</strong>"+
                        " Deleting it will remove ALL related SUSHI credentials consortium-wide.";
              if ( _prov.connection_count > 1 || this.inst_context!=1) {
                var _count = (this.inst_context==1) ? _prov.connection_count : "ALL";
                _title = "You are about to delete "+_count+" provider definitions related to this provider!";
                _notes += "<br /><strong>All related institution-specific definitions for this provider"+
                          " will also be deleted.</strong>";
              }
            } else {
              _notes += "<br />All SUSHI credentials defined for this provider will also be removed.";
            }
            _notes += "</font>";
            notice += _notes;
            Swal.fire({
              title: _title, html: notice, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
            }).then( (result) => {
              if (result.value) {
                // Pass provid (the global ID) and instProvID to the customDestroy method
                // if instProvID=0, it means delete ALL providers related to the global,
                // otherwise, the delete operation will remove just the provider record with ID=instProvID
                let instProvID = provider.id;
                if (this.is_admin && (this.inst_context == 1 ||
                     (this.inst_context !=1 && _prov.connected_count==1 && _prov.is_conso))) {
                  instProvID = "0";
                }
                let url = '/providers/customDestroy/'+provid+"/"+instProvID;
                axios.delete(url)
                     .then( (response) => {
                       if (!response.data.result) {
                         this.success = '';
                         this.failure = response.data.msg;
                         return;
                       }
                       // If we just deleted an inst=specific connection to a consortium provider
                       if ( _prov.is_conso && _prov.connection_count>1 && instProvID!=0) {
                           // update the consortium-provider
                           this.mutable_providers[provIdx].connected = _prov.connected.filter( (prov) => {
                               return prov.inst_id !== provider.inst_id;
                           });
                           this.mutable_providers[provIdx].connection_count =
                              Math.max(this.mutable_providers[provIdx].connection_count-1,0);
                           this.mutable_providers[provIdx].can_connect = this.mutable_providers[provIdx].allow_inst_specific;
                           this.mutable_providers[provIdx].can_edit = (this.is_admin) ? true : false;
                           this.$emit('change-prov', this.mutable_providers[provIdx]);
                       // Reset the mutable_provider record to be an unconnected global
                       } else {
                           this.mutable_providers[provIdx].inst_id = null;
                           this.mutable_providers[provIdx].inst_name = "";
                           this.mutable_providers[provIdx].is_conso = false;
                           this.mutable_providers[provIdx].can_edit = false;
                           this.mutable_providers[provIdx].can_delete = false;
                           this.mutable_providers[provIdx].can_connect = true;
                           this.mutable_providers[provIdx].connected = [];
                           this.mutable_providers[provIdx].connection_count = 0;
                           this.mutable_providers[provIdx].day_of_month = null;
                           this.mutable_providers[provIdx].reports_string = "";
                           this.mutable_providers[provIdx].allow_inst_specific = false;
                           // clear all report_state flags
                           this.mutable_providers[provIdx]['report_state'] = {...this.empty_report_state};
                           this.$emit('change-prov', this.mutable_providers[provIdx]);
                       }
                       this.success = 'Provider successfully disconnected';
                       this.dtKey += 1;           // update the datatable
                     })
                     .catch({});
                }
              })
              .catch({});
        },
        providerImportSubmit (event) {
            this.success = '';
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/providers/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                        this.failure = '';
                        this.success = response.data.msg;
                        // Update provider data from the ProviderController and refresh the table
                        axios.get("/providers?json=1").then((resp2) => {
                            this.mutable_providers = resp2.data.providers;
                            this.dtKey += 1;
                        })
                        .catch(err => console.log(err));
                         this.$emit('bulk-update', this.mutable_providers);
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.providerImportDialog = false;
        },
        doProvExport () {
            let url = "/providers-export";
            if (this.connect_filter != null) {
                let _cf = (this.connect_filter == 'Not Connected') ? 0 : 1;
                url += "?connected="+_cf;
            }
            window.location.assign(url);
        },
        goEdit (provId) {
            window.location.assign('/providers/'+provId+'/edit');
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'is_manager', 'datatable_options']),
      filtered_providers: function() {
        if (this.connect_filter == 'Connected') {
          return this.mutable_providers.filter(p => p.is_conso || p.connected.map(p2 => p2.inst_id).includes(this.inst_context));
        } else if (this.connect_filter == 'Not Connected') {
          return this.mutable_providers.filter(p => p.can_connect && p.connection_count==0);
        } else {
          return [...this.mutable_providers];
        }
      },
      instspec_provids: function() {
        return this.mutable_providers.filter(p => p.inst_id == this.inst_context).map(p2 => p2.id);
      },
      conso_provids: function() {
        return this.mutable_providers.filter(p => p.inst_id == 1).map(p2 => p2.id);
      },
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','providers');

      // If we're viewing providers for a single institution, remove the connected-by column from the datatable
      if (this.inst_context != 1) {
        this.headers.splice(this.headers.findIndex( h => h.text == 'Connected-By'),1);
      }

      // Tack on last 2 columns in the table headers
      if (this.is_admin) {
        this.headers.push({ text: 'Connection Count', value: 'connection_count', align: 'center'} )
      }
      this.headers.push({ text: '', value: 'action', sortable: false });
    },
    mounted() {
      // Set connection-filter options based on context
      let text_0 = (this.inst_context == 1) ? "Consortium Connections" : "Connected";
      this.connect_options = [ {'text': text_0, 'val': "Connected"}, {'text': "Not Connected", 'val': "Not Connected"} ];

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      if (this.inst_context != 1) {
        let context_inst = this.institutions.find(ii => ii.id == this.inst_context);
        this.sushi_insts.push(context_inst);
      }
      console.log('ProviderData Component mounted.');
    }
  }
</script>
<style scoped>
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
.isActive {
  font-style: normal;
}
</style>
