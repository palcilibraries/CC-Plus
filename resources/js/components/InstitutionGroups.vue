<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="importForm">Import Groups</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="4">
          <v-btn small color="primary" @click="createForm">Create a new group</v-btn>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="4">
          <a @click="doExport">
            <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export to Excel
          </a>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <div style="width:50%;">
        <v-data-table :headers="headers" :items="mutable_groups" item-key="id" :options="mutable_options"
                      :key="dtKey" @update:options="updateOptions">
          <template v-slot:item.action="{ item }">
            <span class="dt_action">
              <v-icon title="Edit Group" @click="editForm(item.id)">mdi-cog-outline</v-icon>
              &nbsp; &nbsp;
              <v-icon title="Delete Group" @click="destroy(item.id)">mdi-trash-can-outline</v-icon>
            </span>
          </template>
        </v-data-table>
      </div>
    </div>
    <v-dialog v-model="importDialog" persistent max-width="800px">
      <v-card>
        <v-card-title>Import Institution Groups</v-card-title>
        <v-card-text>
          <v-container grid-list-md>
            <v-row class="d-flex mb-2"><v-col class="d-flex pa-0">
              <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
              ></v-file-input>
            </v-col></v-row>
            <v-row class="d-flex ma-0"><v-col class="d-flex pa-0">
              <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
            </v-col></v-row>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="createDialog" persistent max-width="800px">
      <v-card>
        <v-card-title>Create an Institution Group</v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <v-row class="d-flex ma-0"><v-col class="d-flex pa-0">
                <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              </v-col></v-row>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn x-small color="primary" type="submit" :disabled="form.errors.any()">Save New Group</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="createDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>
    <v-dialog v-model="editDialog" persistent max-width="800px">
      <v-card>
        <v-card-title>Edit an Institution Group</v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
          <v-card-text>
            <v-container grid-list-md>
              <v-row><v-col>
                <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              </v-col></v-row>
              <v-row>
                <v-col>
                  <p><strong>Current Members</strong></p>
                  <template v-for="inst in current_group.institutions">
                    <v-row no-gutters>{{ inst.name }}</v-row>
                  </template>
                </v-col>
                <v-col>
                  <v-autocomplete :items="current_group.not_members" v-model="curInst" return-object
                    item-text="name" item-value="id" label="Add Institution" @change="addInst"
                    hint="Add institution to the group" persistent-hint></v-autocomplete>
                  <v-autocomplete :items="current_group.institutions" v-model="curInst" return-object
                    item-text="name" item-value="id" label="Remove Institution" @change="delInst"
                    hint="Remove institution from the group" persistent-hint></v-autocomplete>
                </v-col>
              </v-row>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save This Group
            </v-btn>
  	        <v-btn small type="button" @click="editDialog=false">cancel</v-btn>
          </v-card-actions>
        </form>
      </v-card>
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
        form: new window.Form({
            name: '',
            institutions: [],
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        importDialog: false,
        createDialog: false,
        editDialog: false,
        import_type: '',
        import_types: ['Full Replacement', 'New Additions']
      }
    },
    methods: {
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.importDialog = true;
            this.createDialog = false;
            this.editDialog = false;
        },
        createForm () {
            this.form.name = '';
            this.createDialog = true;
            this.importDialog = false;
            this.editDialog = false;
        },
        editForm (groupid) {
            this.current_group = this.mutable_groups[this.mutable_groups.findIndex(g=> g.id == groupid)];
            this.form.name = this.current_group.name;
            this.editDialog = true;
            this.createDialog = false;
            this.importDialog = false;
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
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            formData.append('type', this.import_type);
            axios.post('/institutiongroups/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                 })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response groups
                         this.mutable_groups = response.data.groups;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.importDialog = false;
        },
        doExport () {
            window.location.assign('/institutiongroups/export/xlsx');
        },
        // Create a group
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            if (this.editDialog) {
                this.form.institutions = this.current_group.institutions;
                this.form.patch('/institutiongroups/'+this.current_group.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_types record with new value
                            var idx = this.mutable_groups.findIndex(g => g.id == this.current_group.id);
                            Object.assign(this.mutable_groups[idx], response.group);
                            this.success = response.msg;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.editDialog = false;
            } else {
                this.form.post('/institutiongroups')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new group into the mutable array
                        this.mutable_groups.push(response.group);
                        this.mutable_groups.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
                this.createDialog = false;
            }
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
                  axios.delete('/institutiongroups/'+groupid)
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
