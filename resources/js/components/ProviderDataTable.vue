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
                  :key="'mp'+dtKey" item-key="item_key">
      <template v-slot:item.status="{ item }">
        <div v-if="item.conso_id==null">
          <span v-if="item.is_active"><v-icon large color="green" title="Active Global Provider">mdi-toggle-switch</v-icon></span>
          <span v-else><v-icon large color="red" title="Inactive Global Provider">mdi-toggle-switch</v-icon></span>
        </div>
        <div v-else-if="item.is_active">
          <span v-if="is_admin || item.inst_id>1">
            <v-icon large color="green" title="Active" @click="changeStatus(item.conso_id,0)">mdi-toggle-switch</v-icon>
          </span>
          <span v-else><v-icon large color="green" title="Active Consortium Provider">mdi-toggle-switch</v-icon></span>
        </div>
        <div v-else>
          <span v-if="!item.global_prov.is_active">
            <v-icon large color="red" title="Inactive Global Provider">mdi-toggle-switch</v-icon>
          </span>
          <span v-else-if="is_admin || item.inst_id>1">
            <v-icon large color="red" title="Inactive" @click="changeStatus(item.conso_id,1)">mdi-toggle-switch-off</v-icon>
          </span>
          <span v-else><v-icon large color="red" title="Inactive Consortium Provider">mdi-toggle-switch</v-icon></span>
        </div>
      </template>
      <template v-slot:item.name="{ item }">
        <span>
          <v-icon v-if="item.inst_id==1" title="Consortium Provider">mdi-account-multiple</v-icon>
          <v-icon v-else-if="item.inst_id>1" title="Institutional Provider">mdi-home-outline</v-icon>
        </span>
        {{ item.name }}
      </template>
      <template v-slot:item.inst_name="{ item }">
        <span v-if="item.inst_id==1 || inst_context!=1">{{ item.inst_name }}</span>
        <span v-else-if="item.connected.length>1">{{ item.inst_name }} &nbsp;</span>
        <span v-else>
          <a :href="'/institutions/'+item.inst_id" title="View Institution in new tab" target="_blank">{{ item.inst_name }}</a>
        </span>
        <span v-if="item.connected.length>1 || (item.connected.length>0 && item.conso_id==null)">
          <v-icon title="Show Institutions" @click="showConnected(item.id)">mdi-open-in-app</v-icon>
        </span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn v-if="item.can_connect" icon @click="connectProvider(item.id)">
            <v-icon title="Connect Provider">mdi-connection</v-icon>
          </v-btn>
          <v-btn v-if="item.can_edit" icon @click="editProvider(item.conso_id)">
            <v-icon title="Edit Provider">mdi-cog-outline</v-icon>
          </v-btn>
          <v-btn v-else icon><v-icon color="#c9c9c9">mdi-cog-outline</v-icon></v-btn>
          <v-btn v-if="item.can_delete" icon @click="destroy(item.conso_id)">
            <v-icon title="Disconnect Provider">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-else icon>
            <v-icon v-if="item.last_harvest!=null" title="Provider Has Harvests" color="#c9c9c9">mdi-trash-can-outline</v-icon>
            <v-icon v-else-if="item.conso_id==null" title="Global Provider" color="#c9c9c9">mdi-trash-can-outline</v-icon>
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
      <div v-for="inst in cur_provider.connected">
        <v-row class="d-flex mx-2" no-gutters>
          <v-col class="d-flex px-2">
            {{ inst.name }} &nbsp;
            <v-icon title="View institution in new tab" @click="goInst(inst.id)">mdi-open-in-new</v-icon>
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
          { text: 'Master Reports', value: 'reports_string' },
          { text: 'Connected-By', value: 'inst_name' },
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
        dialog_type: 'connect',
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
      }
    },
    methods:{
        providerImportForm () {
            this.csv_upload = null;
            this.provDialog = false;
            this.providerImportDialog = true;
        },
        changeStatus(consoId, state) {
          axios.patch('/providers/'+consoId, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   var _idx = this.mutable_providers.findIndex(p=>p.conso_id == consoId);
                   this.mutable_providers[_idx].is_active = state;
                   this.$emit('change-prov', response.data.provider);
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
            this.cur_provider = Object.assign({},this.mutable_providers.find(p => p.conso_id == provId));
            this.dialog_institutions = [ {'id' : this.cur_provider.inst_id, 'name' : this.cur_provider.inst_name} ];
            this.dialog_type = 'edit';
            this.provDialog = true;
            this.dialogKey += 1;
        },
        connectProvider (provId) {
            this.cur_provider = Object.assign({},this.mutable_providers.find(p => p.id == provId));
            this.cur_provider.day_of_month = 15;
            this.cur_provider.global_id = this.cur_provider.id;
            this.cur_provider.inst_id = this.inst_context;
            this.dialog_institutions = this.institutions.filter( ii => !this.providers.filter(p => (p.id == provId))
                                                                                      .map(p2 => p2.inst_id).includes(ii.id));
            // clear report_state flags - make the user turn on what they want
            Object.keys(this.cur_provider.report_state).forEach( (key) =>  { this.cur_provider.report_state[key] = false; });
            this.dialog_type = 'connect';
            this.provDialog = true;
            this.dialogKey += 1;
        },
        provDialogDone ({ result, msg, prov }) {
            this.success = '';
            this.failure = '';
            if (result == 'Success') {
                // For edit/update , find and replace the provider using conso_id
                if (this.dialog_type == 'edit') {
                  let _idx = this.mutable_providers.findIndex(p => p.id == prov.id);
                  this.mutable_providers.splice(_idx,1,prov);
                  this.$emit('change-prov', this.mutable_providers[_idx]);
                // connect operations differ based on the inst_id being assigned to the new connection
                } else {
                  // connecting to specific inst
                  if (prov.inst_id > 1) {
                    prov.item_key = this.mutable_providers.length + 1;
                    this.mutable_providers.push(prov);
                    this.mutable_providers.sort( (a,b) => {
                        return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
                    });
                    this.$emit('connect-prov', prov);
                    // Add inst to the connected array of the global provider
                    let _glo = this.mutable_providers.findIndex(p => p.id == prov.id && (p.inst_id==1 || p.inst_id==null));
                    if (_glo >= 0) {
                      let _inst = {'id': prov.inst_id, 'name': prov.inst_name};
                      // update global provider connected fields and can_delete
                      this.mutable_providers[_glo].connection_count += 1;
                      this.mutable_providers[_glo].connected.push(_inst);
                      this.$emit('change-prov', this.mutable_providers[_glo]);
                    }
                  // connecting to consortium
                  } else {
                      let _idx = this.mutable_providers.findIndex(p => p.id == prov.id);
                      this.mutable_providers[_idx].inst_id = prov.inst_id;
                      this.mutable_providers[_idx].inst_name = prov.inst_name;
                      this.mutable_providers[_idx].conso_id = prov.conso_id;
                      this.$emit('change-prov', this.mutable_providers[_glo]);
                  }
                }
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
                msg += "Activating these providers also enable related sushi connections, if possible. Sushi connection<br/>";
                msg += "status will be automatically set based on the completeness of the credentials and the active/inactive ";
                msg += "state of any connected institution(s).";
            } else if (Action=='Set Inactive') {
                msg += "Deactivating these providers will stop all future automated harvesting. Any pending or queued<br />";
                msg += "harvesting jobs will not be affected. Any related and sushi credentials will be set to 'Suspended'.";
            } else if (Action=='Connect') {
                msg += "Connecting these providers will add a sushi-setting with empty credentials for each row, and the<br/>";
                msg += "setting(s) will be flagged as 'Incomplete'. Until the required credentials are defined, no report<br />";
                msg += "retrieval will be performed by the CC-Plus automated harvesting system.<br />";
                msg += "Note that any providers already connected will be skipped.";
            } else if (Action=='Disconnect') {
                msg += "CAUTION!!<br />Disconnecting provider records cannot be reversed!! Providers with harvested data<br />";
                msg += "will NOT be changed.<br />";
                msg += " NOTE: ALL sushi definitions associated with the selected providers will also be deleted!";
                if (!this.is_admin) {
                    msg += "<br />Any providers providers restricted by the consortium admin will be skipped.";
                }
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
                      axios.delete('/providers/'+provider.conso_id)
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
                    let consoId = provider.conso_id;
                    if (consoId==null || (!this.is_admin && provider.inst_id==1) || !provider.global_prov.is_active) {
                      skip_count+=1;
                    } else {
                      axios.patch('/providers/'+consoId, { is_active: state })
                           .then( (response) => {
                             if (response.data.result) {
                               var _idx = this.mutable_providers.findIndex(p => p.conso_id == consoId);
                               this.mutable_providers[_idx].is_active = state;
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
                  this.mutable_providers[provIdx].conso_id = response.data.provider.conso_id;
                  this.mutable_providers[provIdx].connected = response.data.provider.connected;
                  this.mutable_providers[provIdx].connectors = response.data.provider.connectors;
                  this.mutable_providers[provIdx].connection_count = response.data.provider.connection_count;
                  this.mutable_providers[provIdx].day_of_month = response.data.provider.day_of_month;
                  this.mutable_providers[provIdx].restricted = response.data.provider.restricted;
                  this.mutable_providers[provIdx].allow_inst_specific = response.data.provider.allow_inst_specific;
                  // Step-2 - If we just connected an inst-specific provider, create a new, stubbed-out connection automatically
                  if (this.inst_context > 1) {
                    var stub = {'inst_id' : this.inst_context, 'prov_id' : response.data.provider.conso_id, };
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
            var provIdx  = this.mutable_providers.findIndex(p => p.conso_id == provid);
            var provider = this.mutable_providers[provIdx];
            var consoIdx = this.mutable_providers.findIndex(p => p.id==provider.id && p.inst_id==1);
            let notice = "Disconnecting a provider cannot be reversed, only manually reconnected."+
                  " Because this provider has no harvested usage data, it can be safely"+
                  " removed. NOTE: All Sushi settings connected to this provider"+
                  " will also be removed.";
            if ( provider.inst_id == 1) {
              notice += "<br /><font color='red'><strong>WARNING - This is a consortium-wide provider</strong>"+
                        " Deleting it will remove ALL existing Sushi settings consortium-wide.</font>";
            }
            Swal.fire({
              title: 'Are you sure?', html: notice, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, proceed'
            }).then( (result) => {
              if (result.value) {
                axios.delete('/providers/'+provid)
                     .then( (response) => {
                       if (!response.data.result) {
                         this.success = '';
                         this.failure = response.data.msg;
                         return;
                       }
                       // consoIdx points to a conso-provider and user just deleted an inst-specific connection?
                       if ( consoIdx >= 0 && provider.inst_id>1 ) {
                           let connected = [ ...this.mutable_providers[consoIdx].connected ];
                           this.mutable_providers[consoIdx].connected = connected.filter( (inst) => {
                               return inst.id !== provider.inst_id;
                           });
                           this.mutable_providers[consoIdx].connection_count =
                              Math.max(this.mutable_providers[consoIdx].connection_count-1,0);
                           this.mutable_providers[consoIdx].can_connect = this.mutable_providers[consoIdx].allow_inst_specific;
                           this.$emit('change-prov', this.mutable_providers[consoIdx]);
                       }
                       this.mutable_providers.splice(provIdx,1);
                       this.$emit('disconnect-prov', provider);
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
      ...mapGetters(['is_admin', 'datatable_options']),
      filtered_providers: function() {
        if (this.connect_filter == 'Connected') {
           return (this.inst_context==1) ? this.mutable_providers.filter(p => p.inst_id==1 && this.conso_provids.includes(p.id))
                                         : this.mutable_providers.filter(p => p.inst_id==this.inst_context ||
                                           (this.conso_provids.includes(p.id) && !this.instspec_provids.includes(p.id)));
        } else if (this.connect_filter == 'Not Connected') {
           return this.mutable_providers.filter(p => (p.can_connect &&
                      !this.mutable_providers.filter(p2 => p2.id == p.id).map(p3 => p3.inst_id).includes(this.inst_context)));
        } else {
          return (this.inst_context==1) ? this.mutable_providers
                                        : this.mutable_providers.filter(p => p.inst_id==this.inst_context ||
                                              ((p.conso_id==null || p.inst_id==1) && !this.instspec_provids.includes(p.id)));
        }
      },
      instspec_provids: function() {
        return this.mutable_providers.filter(p => p.inst_id == this.inst_context).map(p2 => p2.id);
      },
      conso_provids: function() {
        return this.mutable_providers.filter(p => p.inst_id == 1).map(p2 => p2.id);
      },
    },
    beforeCreate() {
      // Load existing store data
      this.$store.commit('initialiseStore');
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
      let text_0 = (this.inst_context == 1) ? "Consortium Connections" : "Institutional Connections";
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
<style>

</style>
