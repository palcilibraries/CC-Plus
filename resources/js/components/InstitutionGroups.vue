<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="showForm==''">
      <v-row>
        <v-col cols="2"><v-btn small color="primary" @click="importForm">Import Groups</v-btn></v-col>
        <v-col><v-btn small color="primary" @click="createForm">Create a new group</v-btn></v-col>
      </v-row>
      <v-row>
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/institutiongroups/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/institutiongroups/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <v-data-table :headers="headers" :items="mutable_groups" item-key="id"  class="elevation-1">
        <template v-slot:item="{ item }">
          <tr>
            <td>{{ item.name }}</td>
            <td>
              <v-btn x-small class='btn btn-primary' type="button" :href="'/institutiongroups/'+item.id+'/edit'">
                  Edit
              </v-btn>
              &nbsp; &nbsp;
              <v-btn x-small class='btn btn-danger' type="button" @click="destroy(item.id)">Delete</v-btn>
            </td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <div v-if="showForm=='import'" style="width:50%; display:inline-block;">
      <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined></v-file-input>
      <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
      <v-btn small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
      <v-btn small type="button" @click="hideForm">cancel</v-btn>
    </div>
    <div v-if="showForm=='create'" style="width:50%; display:inline-block;">
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
            class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save New Group</v-btn>
        <v-btn small type="button" @click="hideForm">cancel</v-btn>
      </form>
    </div>
  </div>
</template>
<script>
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
        showForm: '',
        mutable_groups: this.groups,
        headers: [
          { text: 'Group', value: 'name' },
          { },
        ],
        form: new window.Form({
            name: '',
        }),
        csv_upload: null,
        import_type: '',
        import_types: ['Full Replacement', 'New Additions']
      }
    },
    methods: {
        importForm () {
            this.csv_upload = null;
            this.import_type = '';
            this.showForm = 'import';
        },
        createForm () {
            this.form.name = '';
            this.showForm = 'create';
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
            this.showForm = '';
        },
        // Create a group
        formSubmit (event) {
            this.success = '';
            this.failure = '';
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
            this.showForm = '';
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
        hideForm (event) {
            this.showForm = '';
        },
    }
  }
</script>

<style>
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
