<template>
  <div>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-row class="d-flex mt-2" no-gutters>
      <v-col class="d-flex" cols="9">&nbsp;</v-col>
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
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="connect_filter!=null" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="connect_filter=null"/>&nbsp;
        </div>
        <v-select :items="connect_options" v-model="connect_filter" label="Filter by Connection Status"></v-select>
      </v-col>
    </v-row>
    <v-data-table v-model="selectedRows" :headers="headers" :items="filtered_providers" show-select item-key="id"
                  :options="mutable_options" :search="search" @update:options="updateOptions" :key="'mp'+dtKey">
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
        <span v-if="item.inst_id==1">
          <v-icon title="Consortium Provider">mdi-account-group</v-icon>&nbsp;
        </span>
        {{ item.name }}
      </template>
      <template v-slot:item.inst_name="{ item }">
        <span v-if="item.inst_id==1 || inst_context!=1">{{ item.inst_name }}</span>
        <span v-else-if="item.connected.length>1">
          {{ item.inst_name }} &nbsp;
          <v-icon title="Show Institutions" @click="showConnected(item.id)">mdi-open-in-app</v-icon>
        </span>
        <span v-else><a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a></span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn v-if="item.connected.length==0 || (item.allow_inst_specific && inst_context!=1)"
                 icon @click="connectOne(item.id)">
            <v-icon title="Connect Provider">mdi-connection</v-icon>
          </v-btn>
          <v-btn v-if="item.can_edit" icon @click="editProvider(item.id)">
            <v-icon title="Edit Provider">mdi-cog-outline</v-icon>
          </v-btn>
          <v-btn v-else icon><v-icon color="#c9c9c9">mdi-cog-outline</v-icon></v-btn>
          <v-btn v-if="item.can_delete" icon @click="destroy(item.conso_id)">
            <v-icon title="Disconnect Provider">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-else icon><v-icon color="#c9c9c9">mdi-trash-can-outline</v-icon></v-btn>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="connectedDialog" content-class="ccplus-dialog">
      <h3 align="center">Institutions connected to<br />{{ current_provider.name }}</h3>
      <hr>
      <div v-for="inst in current_provider.connected">
        <v-row class="d-flex mx-2" no-gutters>
          <v-col class="d-flex px-2">
            {{ inst.name }} &nbsp;
            <v-icon title="View institution in new tab" @click="goInst(inst.id)">mdi-open-in-new</v-icon>
          </v-col>
        </v-row>
      </div>
    </v-dialog>
    <v-dialog v-model="provDialog" content-class="ccplus-dialog">
      <provider-dialog dtype="edit" :provider="cur_provider" :institutions="institutions" :master_reports="master_reports"
                       @prov-complete="provDialogDone" :key="dialogKey"
      ></provider-dialog>
    </v-dialog>
    <v-dialog v-model="sushiDialog" content-class="ccplus-dialog">
      <sushi-dialog dtype="create" :institutions="sushi_insts" :providers="sushi_provs" :setting="{}"
                    :all_settings="[]" @sushi-done="sushiDialogDone"
      ></sushi-dialog>
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
        // filtered_providers: [],
        bulk_actions: [ 'Set Active', 'Set Inactive', 'Connect', 'Disconnect' ],
        connect_options: ['Connected', 'Not Connected'],
        bulkAction: null,
        selectedRows: [],
        dtKey: 1,
        dialogKey: 1,
        mutable_options: {},
        search: '',
        new_provider: null,
        cur_provider: {},
        provDialog: false,
        sushiDialog: false,
        connectedDialog: false,
        connect_filter: 'Connected',
        current_provider: {},
        sushi_insts: [],
        sushi_provs: [],
      }
    },
    methods:{
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
            this.cur_provider = this.mutable_providers.find(p => p.id == provId);
            this.provDialog = true;
            this.dialogKey += 1;
        },
        provDialogDone ({ result, msg, prov }) {
            this.success = '';
            this.failure = '';
            if (result == 'Success') {
                let _idx = this.mutable_providers.findIndex(p => p.id == prov.global_id);
                this.mutable_providers[_idx].name = prov.name;
                this.mutable_providers[_idx].is_active = prov.is_active;
                this.mutable_providers[_idx].day_of_month = prov.day_of_month;
                this.mutable_providers[_idx].reports_string = prov.reports_string;
                this.mutable_providers[_idx].restricted = prov.restricted;
                this.mutable_providers[_idx].allow_inst_specific = prov.allow_inst_specific;
                this.success = msg;
                this.dtKey += 1;
                this.$emit('change-prov', prov);
            } else if (result == 'Fail') {
                this.failure = msg;
            } else if (result != 'Cancel') {
                this.failure = 'Unexpected Result returned from dialog - programming error!';
            }
            this.provDialog = false;
        },
        sushiDialogDone ({ result, msg, setting }) {
            this.success = '';
            this.failure = '';
            if (result == 'Created') {
                this.success = msg;
            } else if (result == 'Fail') {
                this.failure = msg;
            } else if (result != 'Cancel') {
                this.failure = 'Unexpected Result returned from sushiDialog - programming error!';
            }
            this.$emit('connect-prov', this.sushi_provs[0]);
            this.sushiDialog = false;
            this.dtKey += 1;
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
                    // let connected_inst = provider.connected.find( ii => ii.id == Context);
                    // if (typeof(connected_inst) != 'undefined') {
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
            this.current_provider = this.mutable_providers.find(p => p.id == id);
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
                  this.mutable_providers[provIdx].inst_id = response.data.provider.inst_id;
                  this.mutable_providers[provIdx].inst_name = response.data.provider.inst_name;
                  this.mutable_providers[provIdx].conso_id = response.data.provider.conso_id;
                  this.mutable_providers[provIdx].connected = response.data.provider.connected;
                  this.mutable_providers[provIdx].connectors = response.data.provider.connectors;
                  this.mutable_providers[provIdx].day_of_month = response.data.provider.day_of_month;
                  this.mutable_providers[provIdx].restricted = response.data.provider.restricted;
                  this.mutable_providers[provIdx].allow_inst_specific = response.data.provider.allow_inst_specific;
                  // Step-2 - If we just connected an inst-specific provider, enable the sushi dialog to ask for credentials
                  if (this.inst_context > 1) {
                    this.sushi_provs = [this.mutable_providers[provIdx]];
                    this.sushiDialog = true;
                  }
                  this.success = provider.name + " successfully connected";
              }
          })
          .catch(error => {});
        },
        destroy (provid) {
            this.success = '';
            this.failure = '';
            var provIdx = this.mutable_providers.findIndex(p => p.conso_id == provid);
            var provider = this.mutable_providers[provIdx];
            Swal.fire({
              title: 'Are you sure?',
              text: "Disconnecting a provider cannot be reversed, only manually reconnected."+
                    " Because this provider has no harvested usage data, it can be safely"+
                    " removed. NOTE: All Sushi settings connected to this provider"+
                    " will also be removed.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                axios.delete('/providers/'+provid)
                     .then( (response) => {
                       if (response.data.result) {
                         provider.inst_id = null;
                         provider.inst_name = null;
                         provider.connected = [];
                         provider.day_of_month = '';
                         provider.can_edit = false;
                         provider.can_delete = false;
                         this.mutable_providers.splice(provIdx,1,provider);
                         this.$emit('disconnect-prov', provider);
                         this.success = 'Provider successfully disconnected';
                         this.dtKey += 1;           // update the datatable
                       } else {
                         this.success = '';
                         this.failure = response.data.msg;
                       }
                     })
                     .catch({});
              }
            }).catch({});
        },
        goEdit (provId) {
            window.location.assign('/providers/'+provId+'/edit');
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'datatable_options']),
      filtered_providers: function() {
        if (this.connect_filter == 'Connected') {
           return this.mutable_providers.filter(p => p.inst_name!=null);
        } else if (this.connect_filter == 'Not Connected') {
           return this.mutable_providers.filter(p => p.inst_name==null);
        } else {
          return [...this.mutable_providers];
        }
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
