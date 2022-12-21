<template>
  <div>
    <div>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="createForm">Create an Institution</v-btn>
        </v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-btn small color="primary" @click="institutionImportForm">Import Institutions</v-btn>
        </v-col>
        <v-col cols="3">&nbsp;</v-col>
        <v-col class="d-flex px-2" cols="3">
          <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" single-line hide-details
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-0">
        <v-col class="d-flex px-2" cols="3">&nbsp;</v-col>
        <v-col class="d-flex px-2" cols="3">
          <a @click="doInstExport">
            <v-icon title="Export to Excel">mdi-microsoft-excel</v-icon>&nbsp; Export Institutions to Excel
          </a>
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
          <v-select :items="status_options" v-model="mutable_filters['stat']" @change="updateFilters('stat')"
                    label="Limit by Status"
          ></v-select> &nbsp;
        </v-col>
        <v-col class="d-flex px-4 align-center" cols="3">
          <div v-if="filters['groups'].length>0" class="x-box">
            <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="filters['groups'] = []"/>&nbsp;
          </div>
          <v-select :items="mutable_groups" v-model="mutable_filters['groups']" @change="updateFilters('groups')" multiple
                    label="Limit by Group(s)"  item-text="name" item-value="id"
          ></v-select>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
      </div>
      <v-data-table v-model="selectedRows" :headers="headers" :items="mutable_institutions" show-select
                    item-key="id" :options="mutable_options" @update:options="updateOptions"
                    :footer-props="footer_props" :search="search" :key="'indt'+dtKey">
        <template v-slot:item.action="{ item }">
          <span class="dt_action">
            <v-btn icon @click="goEdit(item.id)">
              <v-icon title="Edit Institution" >mdi-cog-outline</v-icon>
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
    </div>
    <v-dialog v-model="institutionImportDialog" persistent max-width="1200px">
      <v-card>
        <v-card-title>Import Institutions</v-card-title>
        <v-spacer></v-spacer>
        <v-card-text>
          <v-container grid-list-md>
            <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined
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
            <v-btn x-small color="primary" type="submit" @click="institutionImportSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="institutionImportDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-model="instDialog" persistent max-width="500px">
      <v-card>
        <v-card-title>
          <span>Create a new institution</span>
        </v-card-title>
        <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
              @keydown="form.errors.clear($event.target.name)">
          <v-card-text>
            <v-container grid-list-md>
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
              <div class="field-wrapper">
                <v-subheader v-text="'FTE'"></v-subheader>
                <v-text-field v-model="form.fte" label="FTE" hide-details single-line type="number"></v-text-field>
              </div>
              <div class="field-wrapper has-label">
                <v-subheader v-text="'Belongs To'"></v-subheader>
                <v-select :items="mutable_groups" v-model="form.institutiongroups" item-text="name" item-value="id"
                          label="Institution Group(s)" multiple chips persistent-hint
                          hint="Assign group membership for this institution"
                ></v-select>
              </div>
              <v-textarea v-model="form.notes" label="Notes" auto-grow></v-textarea>
            </v-container>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-col class="d-flex">
              <v-btn class='btn' x-small color="primary" type="submit">Save New Institution</v-btn>
            </v-col>
            <v-col class="d-flex">
              <v-btn class='btn' x-small type="button" color="primary" @click="instDialog=false">Cancel</v-btn>
            </v-col>
          </v-card-actions>
        </form>
      </v-card>
    </v-dialog>
    <v-dialog v-model="groupingDialog" persistent max-width="500px">
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
        institutionImportDialog: false,
        settingsImportDialog: false,
        groupingDialog: false,
        search: '',
        bulk_actions: [ 'Set Active', 'Set Inactive', 'Create New Group', 'Add to Existing Group' ],
        bulkAction: null,
        selectedRows: [],
        groupingType: '',
        newGroupName: '',
        addToGroupID: null,
        mutable_filters: this.filters,
        status_options: ['ALL', 'Active', 'Inactive'],
        headers: [
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Local ID ', value: 'local_id', align: 'start' },
          { text: 'Status', value: 'status' },
          { text: 'Group(s)', value: 'groups' },
          { text: '', value: 'action', sortable: false },
        ],
        footer_props: { 'items-per-page-options': [10,50,100,-1] },
        mutable_institutions: [ ...this.institutions],
        mutable_groups: [ ...this.all_groups],
        form: new window.Form({
            name: '',
            is_active: 1,
            fte: 0,
            institutiongroups: [],
            notes: '',
        }),
        dtKey: 1,
        mutable_options: {},
        csv_upload: null,
        import_type: '',
        import_types: ['Add or Update', 'Full Replacement']
      }
    },
    methods: {
        institutionImportForm () {
            this.csv_upload = null;
            this.instDialog = false;
            this.institutionImportDialog = true;
            this.settingsImportDialog = false;
        },
        settingsImportForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.settingsImportDialog = true;
            this.institutionImportDialog = false;
            this.provDialog = false;
        },
        createForm () {
            this.failure = '';
            this.success = '';
            this.form.name = '';
            this.form.is_active = 1;
            this.form.fte = 0;
            this.form.institutiongroups = [];
            this.form.notes = '';
            this.instDialog = true;
            this.settingsImportDialog = false;
            this.institutionImportDialog = false;
        },
        updateFilters() {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        clearFilter(filter) {
            this.mutable_filters[filter] = [];
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        updateRecords() {
            this.success = "";
            this.failure = "";
            this.loading = true;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/institutions?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_institutions = response.data.institutions;
                 })
                 .catch(err => console.log(err));
            this.loading = false;
        },
        processBulk() {
            this.success = "";
            this.failure = "";
            let msg = "";
            if (this.bulkAction == 'Set Active') {
                msg += "Bulk processing will process each requested institution sequentially.<br><br>";
                msg += "Activating these institutions will affect their Sushi Settings:<br>";
                msg += "<ul><li>Suspended settings will be enabled</li>";
                msg += "<li>Disabled settings will remain disabled</li></ul>";
            } else if (this.bulkAction == 'Set Inactive') {
                msg += "Bulk processing will process each requested institution sequentially.<br><br>";
                msg += "De-Activating these institutions will affect their Sushi Settings:<br>";
                msg += "<ul><li>Active settings will be Suspended</li>";
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
                var state = (this.bulkAction == 'Set Active') ? 1 : 0;
                var new_status = (state == 1) ? 'Active' : 'Inactive';
                this.selectedRows.forEach( (inst) => {
                    // axios.post('/sushisettings-update', {
                    axios.patch('/institutions/'+inst.id, {
                      name: inst.name,
                      is_active: state
                    })
                    .then( (response) => {
                        if (response.data.result) {
                            var _idx = this.mutable_institutions.findIndex(i=>i.id == inst.id);
                            this.mutable_institutions[_idx].is_active = state;
                            this.mutable_institutions[_idx].status = new_status;
                        } else {
                            this.success = '';
                            this.failure = response.data.msg;
                            return false;
                        }
                    }).catch(error => {});
                });
                this.success = "Selected institutions successfully updated.";
              }
              this.bulkAction = '';
              this.dtKey += 1;           // update the datatable
              return true;
          })
          .catch({});
        },
        groupUpdate() {
          this.success = '';
          this.failure = '';
          this.dialog_failure = '';
          let the_group_name = '';
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
            the_group_name = this.newGroupName;
            axios.post('/institutiongroups', {
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
                    this.success = 'New Group: '+the_group_name+' created with '+institution_count+' institutions.';
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
                    the_group_name = response.data.group.name;
                    this.success = 'Added '+response.data.count+' institutions to '+the_group_name;
                } else {
                    this.dialog_failure = response.data.msg;
                    return;
                }
            }).catch(error => {});
          }
          // Update "Group(s)" string for affected institutons
          this.selectedRows.forEach( (inst) => {
            var _idx = this.mutable_institutions.findIndex(i=>i.id == inst.id);
            let group_str = this.mutable_institutions[_idx].groups;
            group_str += (group_str.length > 0) ? ', ' : '';
            group_str += the_group_name;
            this.mutable_institutions[_idx].groups = group_str;
          });
          this.groupingDialog = false;
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
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
             this.institutionImportDialog = false;
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.form.post('/institutions')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new institution onto the mutable array and re-sort it
                        this.mutable_institutions.push(response.institution);
                        this.mutable_institutions.sort((a,b) => {
                          if ( a.name < b.name ) return -1;
                          if ( a.name > b.name ) return 1;
                          return 0;
                        });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            this.instDialog = false;
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
        goEdit (instId) {
            window.location.assign('/institutions/'+instId+'/edit');
        },
        doInstExport () {
            window.location.assign('/institutions/export/xlsx');
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
      ...mapGetters(['all_filters','page_name','datatable_options'])
    },
    beforeCreate() {
      // Load existing store data
      this.$store.commit('initialiseStore');
    },
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','institutions');
    },
    mounted() {
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
