<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="!showForm">
      <v-btn v-if="is_admin" small color="primary" @click="createForm">Create an Institution</v-btn>
      <v-row v-if="is_admin">
        <v-col cols="1">Export to:</v-col>
        <v-col>
            <a :href="'/institutions/export/xls'">.xls</a> &nbsp; &nbsp;
            <a :href="'/institutions/export/xlsx'">.xlsx</a>
        </v-col>
      </v-row>
      <v-data-table :headers="headers" :items="institutions" item-key="id" class="elevation-1">
        <template v-slot:item="{ item }">
          <tr>
            <td><a :href="'/institutions/'+item.id">{{ item.name }}</a></td>
            <td>{{ item.type }}</td>
            <td v-if="item.is_active">Active</td>
            <td v-else>Inactive</td>
            <td>{{ item.groups }}</td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <div v-else style="width:50%; display:inline-block;">
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-select :items="types" v-model="form.type_id" item-text="name" item-value="id"
                  label="Institution Type" outlined
        ></v-select>
        <v-switch v-model="form.is_active" label="Active?"></v-switch>
        <div class="field-wrapper">
            <v-subheader v-text="'FTE'"></v-subheader>
            <v-text-field v-model="form.fte" label="FTE" hide-details single-line type="number"></v-text-field>
        </div>
        <div class="field-wrapper has-label">
            <v-subheader v-text="'Belongs To'"></v-subheader>
            <v-select :items="all_groups" v-model="form.institutiongroups" item-text="name" item-value="id"
                      label="Institution Group(s)" multiple chips persistent-hint
                      hint="Assign group membership for this institution"
            ></v-select>
        </div>
        <v-textarea v-model="form.notes" label="Notes" auto-grow></v-textarea>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
            Save New Institution
          </v-btn>
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
      </form>
    </div>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            types: { type:Array, default: () => [] },
            all_groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        showForm: false,
        headers: [
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Type', value: 'type' },
          { text: 'Status', value: 'is_active' },
          { text: 'Group(s)', value: 'groups' },
        ],
        mutable_institutions: this.institutions,
        form: new window.Form({
            name: '',
            is_active: 0,
            fte: 0,
            type_id: 1,
            institutiongroups: [],
            notes: '',
        })
      }
    },
    methods: {
        createForm () {
            this.failure = '';
            this.success = '';
            this.showForm = true;
            this.form.name = '';
            this.form.is_active = 0;
            this.form.fte = 0;
            this.form.type_id = 1;
            this.form.institutiongroups = [];
            this.form.notes = '';
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.form.post('/institutions')
                .then( (response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new institution onto the mutable array
                        this.mutable_institutions.push(response.institution);
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            this.showForm = false;
        },
        hideForm (event) {
            this.showForm = false;
        },
    },
    computed: {
      ...mapGetters(['is_admin'])
    },
    mounted() {
      console.log('InstitutionData Component mounted.');
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
