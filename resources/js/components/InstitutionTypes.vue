<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="showForm==''">
      <v-row>
        <v-col cols="2"><v-btn small color="primary" @click="importForm">Import Types</v-btn></v-col>
        <v-col><v-btn small color="primary" @click="createForm">Create a new type</v-btn></v-col>
      </v-row>
      <v-row>
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/institutiontypes/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/institutiontypes/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <v-data-table :headers="headers" :items="mutable_types" item-key="id"  class="elevation-1">
        <template v-slot:item="{ item }">
          <tr>
            <td>{{ item.name }}</td>
            <td>
              <v-btn x-small class="btn btn-primary" type="button" @click="editForm(item.id)">Edit</v-btn>
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
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save New Type</v-btn>
        <v-btn small type="button" @click="hideForm">cancel</v-btn>
      </form>
    </div>
    <div v-if="showForm=='edit'" style="width:50%; display:inline-block;">
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
            class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save</v-btn>
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
            types: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        showForm: '',
        current_type: {},
        mutable_types: this.types,
        headers: [
          { text: 'Type', value: 'name' },
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
        editForm (typeid) {
            this.current_type = this.mutable_types[this.mutable_types.findIndex(t=> t.id == typeid)];
            this.form.name = this.current_type.name;
            this.showForm = 'edit';
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
            axios.post('/institutiontypes/import', formData, {
                    headers: {
                        'Content-Type': 'multipart/form-data'
                    }
                 })
                 .then( (response) => {
                     if (response.data.result) {
                         this.failure = '';
                         this.success = response.data.msg;
                         // Replace mutable array with response types
                         this.mutable_types = response.data.types;
                     } else {
                         this.success = '';
                         this.failure = response.data.msg;
                     }
                 });
            this.showForm = '';
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            if (this.showForm == 'edit') {
                this.form.patch('/institutiontypes/'+this.current_type.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_types record with new value
                            var idx = this.mutable_types.findIndex(t => t.id == this.current_type.id);
                            Object.assign(this.mutable_types[idx], response.type);
                            this.success = response.msg;
                        } else {
                            this.failure = response.msg;
                        }
                    });
            } else if (this.showForm == 'create') {
                this.form.post('/institutiontypes')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new type into the mutable array
                        this.mutable_types.push(response.type);
                        this.mutable_types.sort((a,b) => { return a.name.valueOf() > b.name.valueOf(); });
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            }
            this.showForm = '';
        },
        destroy(typeid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "All institutions assigned this type will be reset to type = 1 (Not classified)",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/institutiontypes/'+typeid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               this.mutable_types.splice(this.mutable_types.findIndex(t=> t.id == typeid),1);
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
