<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="createForm">Create a new group</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-btn small color="primary" @click="importForm">Import Groups</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <a @click="doExport">
          <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export to Excel
        </a>
      </v-col>
      <v-col class="d-flex px-2" cols="3">
        <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
        ></v-text-field>
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <div style="width:50%;">
      <v-data-table :headers="headers" :items="mutable_groups" item-key="id" :options="mutable_options"
                    :search="search" :key="dtKey" @update:options="updateOptions">
        <template v-slot:item.action="{ item }">
          <span class="dt_action">
            <v-icon title="Edit Group" @click="editForm(item.id)">mdi-cog-outline</v-icon>
            &nbsp; &nbsp;
            <v-icon title="Delete Group" @click="destroy(item.id)">mdi-trash-can-outline</v-icon>
          </span>
        </template>
        <v-alert slot="no-results" :value="true" color="error" icon="warning">
          Your search for "{{ search }}" found no results.
        </v-alert>
      </v-data-table>
    </div>
    <v-dialog v-model="importDialog" persistent content-class="ccplus-dialog">
      <v-container grid-list-md>
        <v-row class="d-flex ma-2" no-gutters>
          <v-col class="d-flex pa-4 justify-center">
            <h4 align="center">Import Institution Groups</h4>
          </v-col>
        </v-row>
        <v-row class="d-flex ma-2" no-gutters>
          <v-col class="d-flex pa-4">
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
          </v-col>
        </v-row>
        <v-row class="d-flex ma-2" no-gutters>
          <v-col class="d-flex pa-4">
            <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
          </v-col>
        </v-row>
        <v-spacer></v-spacer>
        <v-row class="d-flex ma-2" no-gutters>
          <v-col class="d-flex px-2 justify-center" cols="6">
            <v-btn x-small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex px-2 justify-center" cols="6">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-row>
      </v-container>
    </v-dialog>
    <v-dialog v-model="groupDialog" persistent content-class="ccplus-dialog">
      <v-container grid-list-md>
        <v-form v-model="formValid">
          <v-row class="d-flex ma-2" no-gutters>
            <v-col v-if="dtype=='edit'" class="d-flex pt-4 justify-center"><h4 align="center">Edit Group settings</h4></v-col>
            <v-col v-else class="d-flex pt-4 justify-center"><h4 align="center">Create New Group</h4></v-col>
          </v-row>
          <v-row class="d-flex ma-2" no-gutters>
            <v-text-field v-model="form.name" label="Name" outlined dense></v-text-field>
          </v-row>
          <div v-if="groupDialogType=='edit'">
            <v-row class="d-flex ma-2" no-gutters>
              <v-col class="px-2">
                <p><strong>Current Members</strong></p>
                <template v-for="inst in current_group.institutions">
                  <v-row no-gutters>{{ inst.name }}</v-row>
                </template>
              </v-col>
              <v-col class="px-2">
                <v-autocomplete :items="current_group.not_members" v-model="curInst" return-object
                  item-text="name" item-value="id" label="Add Institution" @change="addInst"
                  hint="Add institution to the group" persistent-hint dense
                ></v-autocomplete>
                <v-autocomplete :items="current_group.institutions" v-model="curInst" return-object
                  item-text="name" item-value="id" label="Remove Institution" @change="delInst"
                  hint="Remove institution from the group" persistent-hint dense
                ></v-autocomplete>
              </v-col>
            </v-row>
          </div>
          <v-spacer></v-spacer>
          <v-row class="d-flex ma-2" no-gutters>
            <v-col class="d-flex px-2 justify-center" cols="6">
              <v-btn class='btn' x-small color="primary" @click="formSubmit" :disabled="!formValid">Save Group</v-btn>
            </v-col>
            <v-col class="d-flex px-2 justify-center" cols="6">
              <v-btn class='btn' x-small type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
            </v-col>
          </v-row>
        </v-form>
      </v-container>
    </v-dialog>
  </div>
</template>
<script>
  import { mapGetters } from 'vuex'
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
            groups: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        current_group: {},
        curInst: {},
        mutable_groups: this.groups,
        headers: [
          { text: 'Group', value: 'name' },
          { text: 'Member Count', value: 'count', align: 'center' },
          { text: '', value: 'action', sortable: false },
        ],
        formValid: true,
        form: new window.Form({
            name: '',
            institutions: [],
        }),
        dtKey: 1,
        search: '',
        mutable_options: {},
        csv_upload: null,
        dtype: null,
        importDialog: false,
        groupDialog: false,
        groupDialogType: '',
        import_type: '',
        import_types: ['Full Replacement', 'New Additions']
      }
    },
    methods: {
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.importDialog = true;
            this.groupDialog = false;
        },
        createForm () {
            this.form.name = '';
            this.importDialog = false;
            this.groupDialog = true;
            this.groupDialogType = 'create';
        },
        editForm (groupid) {
            this.current_group = this.mutable_groups[this.mutable_groups.findIndex(g=> g.id == groupid)];
            this.form.name = this.current_group.name;
            this.importDialog = false;
            this.groupDialog = true;
            this.groupDialogType = 'edit';
        },
        importSubmit (event) {
            this.success = '';
            if (this.import_type == '') {
                this.failure = 'An import type is required';
                return;
            }
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            var membership = [];
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            formData.append('type', this.import_type);
            axios.post('/institution/groups/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                 })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response groups
                         this.mutable_groups = [...response.data.groups];
                         membership = [...response.data.belongsTo];
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.importDialog = false;
            this.$emit('update-groups', {groups: this.mutable_groups, membership: membership});
        },
        doExport () {
            window.location.assign('/institution/groups/export/xlsx');
        },
        // Create a group
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            var membership = [];
            if (this.groupDialogType == 'edit') {
                this.form.institutions = this.current_group.institutions;
                this.form.patch('/institution/groups/'+this.current_group.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_types record with new value
                            var idx = this.mutable_groups.findIndex(g => g.id == this.current_group.id);
                            Object.assign(this.mutable_groups[idx], response.group);
                            membership = [...response.belongsTo];
                            this.success = response.msg;
                            this.$emit('update-groups', {groups: this.mutable_groups, membership: response.belongsTo});
                        } else {
                            this.failure = response.msg;
                            return;
                        }
                    });
            } else if (this.groupDialogType == 'create') {
                this.form.institutions = [];
                this.form.post('/institution/groups')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        membership = [...response.belongsTo];
                        // Add the new group into the mutable array
                        this.mutable_groups.push(response.group);
                        this.mutable_groups.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                        this.$emit('update-groups', {groups: this.mutable_groups, membership: response.belongsTo});
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                        return;
                    }
                });
            }
            this.groupDialog = false;
            this.groupDialogType = '';
        },
        // Delete a group
        destroy(groupid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              // text: "All institutions assigned this group will be reset to group = 1 (Not classified)",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/institution/groups/'+groupid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               this.mutable_groups.splice(this.mutable_groups.findIndex(g=> g.id == groupid),1);
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
        addInst (inst) {
          // Add the entry to the members list and re-sort it
          this.current_group.institutions.push(inst);
          this.current_group.institutions.sort((a,b) => {
              if ( a.name < b.name ) return -1;
              if ( a.name > b.name ) return 1;
              return 0;
          });
          // Remove the setting from the not_members list
          this.current_group.not_members.splice(this.current_group.not_members.findIndex(i=> i.id == inst.id),1);
          this.curInst = {};
        },
        delInst (inst) {
          // Add the entry to the not_members list and re-sort it
          this.current_group.not_members.push(inst);
          this.current_group.not_members.sort((a,b) => {
              if ( a.name < b.name ) return -1;
              if ( a.name > b.name ) return 1;
              return 0;
          });
          // Remove the setting from the not_members list
          this.current_group.institutions.splice(this.current_group.institutions.findIndex(i=> i.id == inst.id),1);
          this.curInst = {};
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
        cancelDialog () {
            this.groupDialog = false;
            this.groupDialogType = '';
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
      this.$store.dispatch('updatePageName','institutiongroups');
  	},
    mounted() {
      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('InstitutionGroups Component mounted.');
    }
  }
</script>

<style>

</style>
