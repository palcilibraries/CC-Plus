<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <div v-if="!showForm">
      <v-btn v-if="is_admin" small color="primary" @click="createForm">Create a Provider</v-btn>
      <v-data-table :headers="headers" :items="mutable_providers" item-key="prov_id" class="elevation-1">
        <template v-slot:item="{ item }">
          <tr>
            <td v-if="is_admin || is_manager">
              <a :href="'/providers/'+item.prov_id">{{ item.prov_name }}</a>
            </td>
            <td v-else>{{ item.prov_name }}</a>
            <td v-if="item.is_active">Active</td>
            <td v-else>Inactive</td>
            <td v-if="item.inst_id==1">Entire Consortium</td>
            <td v-else><a :href="'/institutions/'+item.inst_id">{{ item.inst_name }}</a></td>
            <td>{{ item.day_of_month }}</td>
            <td>&nbsp;</td>
          </tr>
        </template>
      </v-data-table>
    </div>
    <div v-else style="width:50%; display:inline-block;">
      <h4>Create a new Provider</h4>
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
        <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
        <v-switch v-model="form.is_active" label="Active?"></v-switch>
        <div v-if="is_admin">
            <v-select outlined required :items="institutions" v-model="form.inst_id" value="current_user.inst_id"
                      label="Institution" item-text="name" item-value="id"
            ></v-select>
        </div>
        <div v-else>
            <v-text-field outlined readonly label="Institution" :value="inst_name"></v-text-field>
        </div>
        <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
        <div class="field-wrapper has-label">
            <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
            <v-text-field v-model="form.day_of_month" label="Day-of-Month" hide-details single-line type="number"
            ></v-text-field>
        </div>
        <div class="field-wrapper has-label">
            <v-subheader v-text="'Reports to Harvest'"></v-subheader>
            <v-select :items="master_reports" v-model="form.master_reports" value="provider.reports" item-text="name"
                      item-value="id" label="Select" multiple chips hint="Choose which reports to harvest"
                      persistent-hint
            ></v-select>
        </div>
        <p>&nbsp;</p>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
          Save New Provider
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
            providers: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            master_reports: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        inst_name: '',
        showForm: false,
        headers: [
          { text: 'Provider ', value: 'prov_name', align: 'start' },
          { text: 'Status', value: 'is_active' },
          { text: 'Serves', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month' },
          { text: 'Action', value: '' },
        ],
        mutable_providers: this.providers,
        form: new window.Form({
            name: '',
            inst_id: null,
            is_active: 0,
            server_url_r5: '',
            day_of_month: 15,
            master_reports: [],
        })
      }
    },
    methods:{
        createForm () {
            this.failure = '';
            this.success = '';
            this.showForm = true;
            this.form.name = '';
            this.form.inst_id = (this.is_admin) ? null : this.institutions[0].id;
            this.form.is_active = 0;
            this.form.server_url_r5 = '';
            this.form.day_of_month = 15;
            this.form.master_reports = [];
        },
        hideForm (event) {
            this.showForm = false;
        },
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            this.form.post('/providers')
                .then((response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                        // Add the new provider onto the mutable array
                        this.mutable_providers.push(response.provider);
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
            this.showForm = false;
        },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin'])
    },
    mounted() {
      if (!this.is_admin) {
          this.inst_name = this.institutions[0].name;
      }
      console.log('ProviderData Component mounted.');
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
