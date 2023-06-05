<template>
  <div>
    <v-row class="d-flex mt-2" no-gutters>
      <v-col v-if="mutable_unset.length > 0" class="d-flex pa-0" cols="3">
        <v-autocomplete :items="mutable_unset" v-model="new_provider" label="Unconnected Providers"
                        item-text="name" item-value="id" outlined
        ></v-autocomplete>
      </v-col>
      <v-col v-else class="d-flex" cols="3">&nbsp;</v-col>
      <v-col v-if="new_provider != null" class="d-flex px-2" cols="1">
        <v-btn small color="primary" @click="connectUnset">Connect</v-btn>
      </v-col>
      <v-col v-else  class="d-flex px-2" cols="1">&nbsp;</v-col>
      <v-col cols="5">&nbsp;</v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
        ></v-text-field>
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table :headers="headers" :items="mutable_providers" item-key="id" :options="mutable_options"
                  :search="search" @update:options="updateOptions" :key="'mp'+dtKey">
      <template v-slot:item.status="{ item }">
        <span v-if="item.is_active">
          <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
        </span>
        <span v-else-if="item.global_prov.is_active">
          <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
        </span>
        <span v-else>
          <v-icon large color="red" title="Inactive Global Provider">mdi-toggle-switch-off</v-icon>
        </span>
      </template>
      <template v-slot:item.inst_name="{ item }">
        <span v-if="item.inst_id==1">{{ item.inst_name }}</span>
        <span v-else><a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a></span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-icon title="Edit Provider" @click="editProvider(item.id)">mdi-cog-outline</v-icon>
          <v-btn v-if="item.can_delete" icon class="pl-4" @click="destroy(item.id)">
            <v-icon title="Delete Provider">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-else icon class="pl-4">
            <v-icon color="#c9c9c9">mdi-trash-can-outline</v-icon>
          </v-btn>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="provDialog" persistent content-class="ccplus-dialog">
      <provider-dialog dtype="edit" :provider="cur_provider" :institutions="institutions" :master_reports="master_reports"
                       @prov-complete="provDialogDone" :key="dialogKey"
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
            unset_global: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
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
          { text: 'Serves', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month', align: 'center' },
          { text: '', value: 'action', sortable: false }
        ],
        mutable_unset: [ ...this.unset_global ],
        mutable_providers: [ ...this.providers ],
        dtKey: 1,
        dialogKey: 1,
        mutable_options: {},
        search: '',
        new_provider: null,
        cur_provider: {},
        provDialog: false,
      }
    },
    methods:{
        connectUnset () {
            this.failure = '';
            this.success = '';
            // Connect provider consortium-wide
            axios.post('/providers/connect', {
                prov_id: this.new_provider,
                inst_id: 1
            })
            .then( (response) => {
                if (response.data.result) {
                    this.success = response.data.msg;
                    // Update the mutable_providers (connected ones) and the unset globals list
                    this.mutable_providers.push(response.data.added);
                    this.mutable_unset.splice(this.mutable_unset.findIndex(p=> p.id == response.data.added.global_id),1);
                    // re-sort the mutable providers (the main display list)
                    this.mutable_providers.sort((a,b) => {
                        if ( a.name < b.name ) return -1;
                        if ( a.name > b.name ) return 1;
                        return 0;
                    });
                    this.new_provider = null;
                    this.$emit('connect-prov', response.data.added);
                } else {
                    this.failure = response.data.msg;
                }
            })
            .catch(error => {});
            this.dtKey += 1;    // re-render the datatable
        },
        changeStatus(provId, state) {
          axios.patch('/providers/'+provId, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   var _idx = this.mutable_providers.findIndex(p=>p.id == provId);
                   this.mutable_providers[_idx].is_active = state;
                   this.$emit('change-prov', provId);
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
                let _idx = this.mutable_providers.findIndex(p=> p.id == prov.id);
                this.mutable_providers[_idx] = prov;
                this.success = msg;
                this.dtKey += 1;
                this.$emit('change-prov', prov.id);
            } else if (result == 'Fail') {
                this.failure = msg;
            } else if (result != 'Cancel') {
                this.failure = 'Unexpected Result returned from dialog - programming error!';
            }
            this.provDialog = false;
        },
        destroy (provid) {
            Swal.fire({
              title: 'Are you sure?',
              text: "Deleting a provider cannot be reversed, only manually reconnected."+
                    " Because this provider has no harvested usage data, it can be safely"+
                    " removed. NOTE: All SUSHI settings connected to this provider"+
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
                               this.success = 'Provider successfully deleted';
                               this.mutable_providers.splice(this.mutable_providers.findIndex(p=>p.id == provid),1);
                               this.$emit('disconnect-prov', { provid, global_prov: response.data.global_provider });
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
        goEdit (provId) {
            window.location.assign('/providers/'+provId+'/edit');
        },
    },
    computed: {
      ...mapGetters(['datatable_options'])
    },
    beforeCreate() {
        // Load existing store data
		this.$store.commit('initialiseStore');
	},
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','providers');
	},
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('ProviderData Component mounted.');
    }
  }
</script>
<style>

</style>
