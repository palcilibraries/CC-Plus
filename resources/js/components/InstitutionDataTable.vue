<template>
  <div>
    <v-row class="d-flex mb-1 align-end" no-gutters>
      <v-col class="d-flex px-1" cols="3">
        <v-btn small color="primary" @click="createForm">Create an Institution</v-btn>
      </v-col>
      <v-col class="d-flex px-1" cols="3">
        <v-btn small color="primary" @click="institutionImportForm">Import Institutions</v-btn>
      </v-col>
      <v-col class="d-flex px-1" cols="3">
        <a @click="doInstExport">
          <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Institutions to Excel
        </a>
      </v-col>
      <v-col class="d-flex px-1" cols="3">
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
        <div v-if="mutable_filters['stat'] != ''" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('stat')"/>&nbsp;
        </div>
        <v-select :items="status_options" v-model="mutable_filters['stat']" @change="updateFilters('stat')"
                  label="Limit by Status"
        ></v-select> &nbsp;
      </v-col>
      <v-col class="d-flex px-4 align-center" cols="3">
        <div v-if="mutable_filters['groups'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('groups')"/>&nbsp;
        </div>
        <v-autocomplete :items="mutable_groups" v-model="mutable_filters['groups']" @change="updateFilters('groups')" multiple
                  label="Limit by Group(s)"  item-text="name" item-value="id">
          <template v-slot:prepend-item>
            <v-list-item @click="changeAllGroups">
               <span v-if="allGroups">Clear Selections</span>
               <span v-else>Enable All</span>
            </v-list-item>
            <v-divider class="mt-1"></v-divider>
          </template>
          <template v-slot:selection="{ item, index }">
            <span v-if="index==0 && mutable_groups.length==mutable_filters['groups'].length">All Groups</span>
            <span v-else-if="index==0 && !allGroups">{{ item.name }}</span>
            <span v-else-if="index===1 && !allGroups" class="text-grey text-caption align-self-center">
              &nbsp; +{{ mutable_filters['groups'].length-1 }} more
            </span>
          </template>
        </v-autocomplete>
      </v-col>
    </v-row>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_institutions" show-select
                  item-key="id" :options="mutable_options" @update:options="updateOptions"
                  :footer-props="footer_props" :search="search" :key="'indt'+dtKey">
      <template v-slot:item.name="{ item }">
        <span v-if="item.is_active==0" class="isInactive">{{ item.name }}</span>
        <span v-else>{{ item.name }}</span>
      </template>
      <template v-slot:item.status="{ item }">
        <span v-if="item.is_active">
          <v-icon large color="green" title="Active" @click="changeStatus(item.id,0)">mdi-toggle-switch</v-icon>
        </span>
        <span v-else>
          <v-icon large color="red" title="Inactive" @click="changeStatus(item.id,1)">mdi-toggle-switch-off</v-icon>
        </span>
      </template>
      <template v-slot:item.action="{ item }">
        <span class="dt_action">
          <v-btn icon @click="goEdit(item.id)">
            <v-icon title="Edit Institution" >mdi-open-in-new</v-icon>
          </v-btn>
          <v-btn v-if="item.can_delete" icon class="pl-4" @click="destroy(item.id)">
            <v-icon title="Delete Institution">mdi-trash-can-outline</v-icon>
          </v-btn>
          <v-btn v-if="!item.can_delete" icon class="pl-4">
            <v-icon color="#c9c9c9">mdi-trash-can-outline</v-icon>
          </v-btn>
        </span>
      </template>
      <v-alert slot="no-results" :value="true" color="error" icon="warning">
        Your search for "{{ search }}" found no results.
      </v-alert>
    </v-data-table>
    <v-dialog v-model="institutionImportDialog" max-width="1200px">
      <v-card>
        <v-card-title>Import Institutions</v-card-title>
        <v-spacer></v-spacer>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File (CSV)" v-model="csv_upload" accept="text/csv" outlined
            ></v-file-input>
            <p>
              <strong>Note:&nbsp; Institution imports function exclusively as Updates. No existing institution
              records will be deleted.</strong>
            </p>
            <p>
              The import process evaluates input rows to determine if a row defines an existing, or new, institution.
              A match for an existing institution depends (first) on matching a System-ID in column-A. If column-A is
              missing or empty, a match will be based on the Local-ID in column-B. Any Import rows with no System-ID in
              in column A and no Local-ID in column B will be ignored. If no matches are found by searching for an
              existing institution using column-A and column-B, the import row will be treated as a NEW institution.
            </p>
            <p>
              New institutions being added via the import function must also contain a unique name. If the import
              row also contains a value for Local-ID, this value must also be unique since a match for Local-ID will
              result in an UPDATE and not an INSERT operation (this will modify the matching institition data to
              match the cells in the row possibly intended as a new institution.)
            </p>
            <p>
              Institutions can be renamed via import by giving a CC+ System ID-# in column-A and/or a Local-ID (in
              column-B) with a replacement name in column-C. Be aware that the new name takes effect immediately,
              and will be associated with all harvested usage data that may have been collected using the OLD name
              (data is stored by the System ID, not the name.)
            </p>
            <p>
              For these reasons, use caution when using this import function. Generating an Institution export FIRST
              will supply detailed instructions for importing on the "How to Import" tab. Generating a new Institution
              export AFTER an import operation is a good way to confirm that all the settings are as-desired.
            </p>
          </v-container>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="institutionImportSubmit" :disabled="csv_upload==null"
            >Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="institutionImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="instDialog" content-class="ccplus-dialog">
      <institution-dialog dtype="create" :groups="all_groups" @inst-complete="instDialogDone" :key="idKey"></institution-dialog>
    </v-dialog>
    <v-dialog v-model="groupingDialog" max-width="500px">
      <v-card>
        <v-card-title>
          <span v-if="groupingType=='Create'">Create a New Institution Group</span>
          <span v-if="groupingType=='Add'">Add Institutions to An Existing Group</span>
        </v-card-title>
        <form method="POST" action="" @submit.prevent="groupUpdate" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <div class="status-message" v-if="dialog_failure">
                <span v-if="dialog_failure" class="fail" role="alert" v-text="dialog_failure"></span>
              </div>
              <div v-if="groupingType=='Create'">
                 <v-text-field v-model="newGroupName" label="Name" outlined></v-text-field>
              </div>
              <div v-if="groupingType=='Add'">
                <v-select :items="mutable_groups" v-model="addToGroupID" item-text="name" item-value="id"
                          label="Institution Group" hint="Add institutions to this group"
                ></v-select>
              </div>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn class='btn' x-small color="primary" type="submit">Submit</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="groupingDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import Swal from 'sweetalert2';
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} }
           },
    data () {
      return {
        success: '',
        failure: '',
        dialog_failure: '',
        instDialog: false,
        idKey: 0,
        institutionImportDialog: false,
        settingsImportDialog: false,
        groupingDialog: false,
        search: '',
        bulk_actions: [ 'Set Active', 'Set Inactive', 'Create New Group', 'Add to Existing Group', 'Delete' ],
        bulkAction: null,
        selectedRows: [],
        groupingType: '',
        newGroupName: '',
        allGroups: false,
        addToGroupID: null,
        status_options: ['ALL', 'Active', 'Inactive'],
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Local ID ', value: 'local_id', align: 'start' },
          { text: 'Group(s)', value: 'group_string' },
          { text: '', value: 'action', sortable: false },
        ],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        mutable_institutions: [ ...this.institutions],
        mutable_groups: [ ...this.all_groups],
        mutable_filters: {'stat': "", 'groups': []},
        form: new window.Form({
            name: '',
            is_active: 1,
            fte: 0,
            institution_groups: [],
            notes: '',
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement'],
        added_insts: [],
      }
    },
    methods: {
        institutionImportForm () {
            this.csv_upload = null;
            this.instDialog = false;
            this.settingsImportDialog = false;
            this.institutionImportDialog = true;
        },
        settingsImportForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.instDialog = false;
            this.institutionImportDialog = false;
            this.settingsImportDialog = true;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.settingsImportDialog = false;
            this.institutionImportDialog = false;
            this.instDialog = true;
        },
        updateFilters(filt) {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            // check/update allGroups flag
            if (filt == 'groups') {
                this.allGroups = (this.mutable_filters['groups'].length > 0 &&
                      this.mutable_filters['groups'].length == this.mutable_groups.length);
            }
            this.updateRecords();
        },
        changeAllGroups() {
          // Turned allGroups OFF?
          if (this.allGroups) {
            this.mutable_filters['groups'] = [];
            this.allGroups = false;
          // Turned allGroups ON?
          } else {
            this.mutable_filters['groups'] = [...this.mutable_groups];
            this.allGroups = true;
          }
        },
        clearFilter(filter) {
            this.mutable_filters[filter] = (filter == 'stat') ? '' : [];
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.allGroups = false;
            this.updateRecords();
        },
        updateRecords() {
            this.success = "";
            this.failure = "";
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/institutions?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_institutions = response.data.institutions;
                 })
                 .catch(err => console.log(err));
        },
        changeStatus(instId, state) {
          axios.patch('/institutions/'+instId, { is_active: state })
               .then( (response) => {
                 if (response.data.result) {
                   var _idx = this.mutable_institutions.findIndex(ii=>ii.id == instId);
                   this.mutable_institutions[_idx].is_active = state;
                   this.$emit('change-inst', instId);
                 }
               })
               .catch(error => {});
        },
        instDialogDone ({ result, msg, inst }) {
            this.success = '';
            this.failure = '';
            if (result != 'Cancel') {
                if (result == 'Success') {
                    this.success = msg;
                    // Add the new institution onto the mutable array and re-sort it
                    this.mutable_institutions.push(inst);
                    this.mutable_institutions.sort((a,b) => {
                      if ( a.name < b.name ) return -1;
                      if ( a.name > b.name ) return 1;
                      return 0;
                    });
                    // apply new new institution ID to the user form; don't change if already set
                    if (this.form.inst_id == null) {
                        this.form.inst_id = inst.id;
                        this.form_key += 1;
                    }
                    // Add inst to the array we'll emit to caller
                    this.added_insts.push(inst);
                    // return new inst as a 1-item array
                    var new_inst = [];
                    new_inst.push(inst);
                    this.$emit('new-inst', new_inst);
                } else if (result == 'Fail') {
                    this.failure = msg;
                } else  {
                    this.failure = 'Unexpected Result returned from dialog - programming error!';
                }
            }
            this.instDialog = false;
            this.dtKey += 1;
            this.idKey += 1;
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "";
            if (this.bulkAction == 'Delete') {
                msg += "Bulk processing will delete each marked institution sequentially.<br>";
                msg += "Deleting institutions cannot be reversed, they can only be manually recreated.<br><br>";
                msg += "Any institution that has harvested usage data will be skipped and left unchanged.<br>";
                msg += "NOTE: All users and SUSHI credentials connected to these institutions will also be removed.";
            } else if (this.bulkAction == 'Set Active') {
                msg += "Bulk processing will process each requested institution sequentially.<br><br>";
                msg += "Activating these institutions will affect their SUSHI credentials:<br>";
                msg += "<ul><li>Suspended credentials will be enabled</li>";
                msg += "<li>Disabled credentials will remain disabled</li></ul>";
            } else if (this.bulkAction == 'Set Inactive') {
                msg += "Bulk processing will process each requested institution sequentially.<br><br>";
                msg += "De-Activating these institutions will affect their SUSHI credentials:<br>";
                msg += "<ul><li>Active credentials will be Suspended</li>";
                msg += "<li>Queued harvests will NOT be cancelled.</li></ul>";
            } else if (this.bulkAction == 'Create New Group') {
                this.groupingType = 'Create';
                this.groupingDialog = true;
                this.bulkAction = '';
                return true;
            } else if (this.bulkAction == 'Add to Existing Group') {
                this.groupingType = 'Add';
                this.groupingDialog = true;
                this.bulkAction = '';
                return true;
            } else {
                this.failure = "Unrecognized Bulk Action in processBulk!";
                return;
            }
            Swal.fire({
              title: 'Are you sure?', html: msg, icon: 'warning', showCancelButton: true,
              confirmButtonColor: '#3085d6', cancelButtonColor: '#d33', confirmButtonText: 'Yes, Proceed!'
            })
            .then((result) => {
              if (result.value) {
                this.success = "Working...";
                if (this.bulkAction == 'Delete') {
                  var skipped = 0;
                  this.selectedRows.forEach( (inst) => {
                    if (!inst.can_delete) {
                        skipped++;
                        return;
                    }
                    axios.delete('/institutions/'+inst.id)
                         .then( (response) => {
                           if (response.data.result) {
                             this.mutable_institutions.splice(this.mutable_institutions.findIndex(ii=> inst.id == ii.id),1);
                           }
                         })
                         .catch({});
                  });
                  this.success = "Selected Insitutions Deleted";
                  this.success += (skipped>0) ? " ("+skipped+" skipped)" : "";
                } else {
                  var state = (this.bulkAction == 'Set Active') ? 1 : 0;
                  var new_status = (state == 1) ? 'Active' : 'Inactive';
                  this.selectedRows.forEach( (inst) => {
                    axios.patch('/institutions/'+inst.id, { name: inst.name, is_active: state })
                         .then( (response) => {
                           if (response.data.result) {
                               var _idx = this.mutable_institutions.findIndex(i=>i.id == inst.id);
                               this.mutable_institutions[_idx].is_active = state;
                               this.mutable_institutions[_idx].status = new_status;
                           } else {
                               this.success = '';
                               this.failure = response.data.msg;
                           }
                         })
                         .catch(error => {});
                    });
                  this.success = "Selected institutions successfully updated.";
                }
                this.$emit('bulk-update', this.mutable_institutions);
                this.bulkAction = '';
                this.dtKey += 1;           // update the datatable
                return true;
              }
            })
            .catch({});
        },
        groupUpdate() {
          this.success = '';
          this.failure = '';
          this.dialog_failure = '';
          let institution_count = this.selectedRows.length;
          if (this.groupingType == "Create") {
            if (this.newGroupName==null || this.newGroupName=='' ) {
                this.dialog_failure = 'Group name is required';
                return;
            }
            if (this.mutable_groups.findIndex( g => g.name == this.newGroupName) >= 0) {
                this.dialog_failure = 'Group name already exists!';
                return;
            }
            axios.post('/institution/groups', {
              name: this.newGroupName,
              institutions: this.selectedRows
            })
            .then( (response) => {
                if (response.data.result) {
                    // Add the group to the mutable lits
                    this.mutable_groups.push(response.data.group);
                    this.mutable_groups.sort((a,b) => {
                      if ( a.name < b.name ) return -1;
                      if ( a.name > b.name ) return 1;
                      return;
                    });
                    response.data.group.institutions.forEach( (inst) => {
                      var _idx = this.mutable_institutions.findIndex(i=>i.id == inst.id);
                      this.mutable_institutions[_idx].group_string = inst.group_string;
                      this.mutable_institutions[_idx].groups.push(response.data.group.id);
                    });
                    this.$emit('refresh-groups', {groups: this.mutable_groups, insts: this.mutable_institutions});
                    this.success = 'New Group: '+this.newGroupName+' created with '+institution_count+' institutions.';
                } else {
                    this.dialog_failure = response.data.msg;
                    return;
                }
            }).catch(error => {});
          } else if (this.groupingType == "Add") {
            axios.post('/extend-institution-group', {
              id: this.addToGroupID,
              institutions: this.selectedRows
            })
            .then( (response) => {
                if (response.data.result) {
                    var g_idx = this.mutable_groups.findIndex(g=>g.id == this.addToGroupID);
                    this.mutable_groups[g_idx] = {...response.data.group};
                    response.data.group.institutions.forEach( (inst) => {
                      var _idx = this.mutable_institutions.findIndex(i=>i.id == inst.id);
                      this.mutable_institutions[_idx].group_string = inst.group_string;
                      this.mutable_institutions[_idx].groups.push(response.data.group.id);
                    });
                    this.$emit('refresh-groups', {groups: this.mutable_groups, insts: this.mutable_institutions});
                    this.success = 'Added '+response.data.count+' institutions to '+response.data.group.name;
                } else {
                    this.dialog_failure = response.data.msg;
                    return;
                }
            }).catch(error => {});
          }
          this.groupingDialog = false;
          this.dtKey += 1;
        },
        institutionImportSubmit (event) {
            this.success = '';
            if (this.csv_upload==null) {
                this.failure = 'A CSV import file is required';
                return;
            }
            this.failure = '';
            let formData = new FormData();
            formData.append('csvfile', this.csv_upload);
            axios.post('/institutions/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                  })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response institutions
                         this.mutable_institutions = response.data.inst_data;
                         this.$emit('bulk-update', this.mutable_institutions);
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.institutionImportDialog = false;
        },
        destroy (instid) {
            Swal.fire({
              title: 'Are you sure?',
              text: "Deleting an institution cannot be reversed, only manually recreated."+
                    " Because this institution has no harvested usage data, it can be safely"+
                    " deleted. NOTE: All users and SUSHI credentials connected to this institution"+
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
                               this.success = 'Institution Deleted';
                               this.mutable_institutions.splice(this.mutable_institutions.findIndex(ii=> instid == ii.id),1);
                               this.$emit('drop-inst', instid);
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
        goEdit (instId) {
            window.open('/institutions/'+instId+'/edit', "_blank");
        },
        doInstExport () {
            let url = "/institutions-export";
            if (this.mutable_filters['stat']!='' || this.mutable_filters['groups'].length > 0) {
                url += "?filters="+JSON.stringify(this.mutable_filters);
            }
            window.location.assign(url);
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
        isEmpty(obj) {
          for (var i in obj) return false;
          return true;
        }
    },
    computed: {
      ...mapGetters(['all_filters','page_name','datatable_options'])
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','institutions');
    },
    mounted() {
      if ( this.isEmpty(this.filters) ) {
          this.mutable_filters = { stat: '', groups: [] };
      } else {
          this.mutable_filters = { ...this.filters };
      }

      // Apply any defined prop-based filters (and overwrite existing store values)
      var count = 0;
      Object.assign(this.mutable_filters, this.all_filters);
      Object.keys(this.filters).forEach( (key) =>  {
        if (this.filters[key] != null) {
          if (this.filters[key].length>0) {
            count++;
            this.mutable_filters[key] = this.filters[key];
          }
        }
      });

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);

      // Update store and apply filters if some have been set
      if (count>0) this.$store.dispatch('updateAllFilters',this.mutable_filters);

      // Load settings
      this.updateRecords();
      this.dtKey += 1;           // update the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('InstitutionData Component mounted.');
    }
  }
</script>
<style scoped>
.isInactive {
  cursor: pointer;
  color: #999999;
  font-style: italic;
}
</style>
