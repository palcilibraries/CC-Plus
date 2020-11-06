<template>
  <div>
  <template v-if="(is_manager || is_admin) && mutable_unset.length > 0">
	  <form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
	        @keydown="form.errors.clear($event.target.name)">
        <input v-model="form.prov_id" id="prov_id" type="hidden">
	    <v-select
	          :items="mutable_unset"
			  v-model="form.inst_id"
	          @change="onUnsetChange"
	          placeholder="Connect an Institution"
	          item-text="name"
	          item-value="id"
	          outlined
	    ></v-select>
		<div v-if="showForm" class="form-fields">
            <v-text-field v-model="form.customer_id"
                          label="Customer ID"
                          id="customer_id"
                          outlined
            ></v-text-field>
            <v-text-field v-model="form.requestor_id"
                          label="Requestor ID"
                          id="requestor_id"
                          outlined
            ></v-text-field>
            <v-text-field v-model="form.API_key"
                          label="API Key"
                          id="API_key"
                          outlined
            ></v-text-field>
			<v-btn small color="primary" type="submit" :disabled="form.errors.any()">Connect</v-btn>
            <v-btn small color="secondary" type="button" @click="testSettings">Test Settings</v-btn>
			<v-btn small type="button" @click="hideForm">cancel</v-btn>
            <div v-if="showTest">
            	<div>{{ testStatus }}</div>
            	<div v-for="row in testData">{{ row }}</div>
			</div>
		</div>
	  </form>
	</template>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-data-table :headers="headers" :items="mutable_settings" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td><a :href="'/institutions/'+item.institution.id">{{ item.institution.name }}</a></td>
          <td>{{ item.customer_id }}</td>
          <td>{{ item.requestor_id }}</td>
          <td>{{ item.API_key }}</td>
          <td v-if="is_manager || is_admin">
            <v-btn class='btn btn-danger' small type="button" @click="destroy(item)">Delete connection</v-btn>
          </td>
          <td v-if="is_manager || is_admin">
            <v-btn class='btn' small type="button" :href="'/sushisettings/'+item.id+'/edit'">Settings & harvests</v-btn>
          </td>
        </tr>
      </template>
      <tr v-if="is_manager || is_admin"><td colspan="6">&nbsp;</td></tr>
      <tr v-else><td colspan="4">&nbsp;</td></tr>
    </v-data-table>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    import axios from 'axios';
    window.Form = Form;

    export default {
        props: {
                settings: { type:Array, default: () => [] },
                unset: { type:Array, default: () => [] },
                prov_id: { type:Number, default: 0 }
               },
        data() {
            return {
                success: '',
                failure: '',
                testData: '',
                testStatus: '',
				showForm: false,
                showTest: false,
                mutable_settings: this.settings,
                mutable_unset: this.unset,
                headers: [
                  { text: 'Name ', value: 'name' },
                  { text: 'Customer ID', value: 'customer_id' },
                  { text: 'Requestor ID', value: 'requestor_id' },
                  { text: 'API Key', value: 'API_key' },
                  { text: '', value: ''},
				  { text: '', value: ''}
                ],
                form: new window.Form({
                    inst_id: '0',
                    prov_id: this.prov_id,
                    customer_id: '',
                    requestor_id: '',
                    API_key: ''
				})
            }
        },
        methods: {
	        formSubmit (event) {
                this.form.post('/sushisettings')
	                .then((response) => {
                        if (response.result) {
                            this.failure = '';
                            this.success = response.msg;
                            // Add the new connection to the settings rows and sort it by-name ascending
                            this.mutable_settings.push(response.setting);
                            this.mutable_settings.sort((a,b) => {
                                return a.institution.name.valueOf() > b.institution.name.valueOf();
                            });
                            // Remove the unset row that just got added
                            let newid = response.setting.inst_id;
                            this.mutable_unset.splice(this.mutable_unset.findIndex(s=> s.id == newid),1);
                            this.form.inst_id = '0';
                            this.form.prov_id = this.prov_id;
                            this.form.customer_id = '';
                            this.form.requestor_id = '';
                            this.form.API_key = '';
                            this.showForm = false;
                        } else {
                            this.success = '';
                            this.failure = response.msg;
                        }
	                });
	        },
            destroy (setting) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting these settings cannot be reversed, only manually recreated.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/sushisettings/'+setting.id)
                           .then( (response) => {
                               if (response.data.result) {
                                   this.failure = '';
                                   this.success = response.data.msg;
                               } else {
                                   this.success = '';
                                   this.failure = response.data.msg;
                               }
                           })
                           .catch({});
                      // Add the entry to the "unset" list
                      this.mutable_unset.push({'id': setting.inst_id, 'name': setting.institution.name});
                      this.mutable_unset.sort((a,b) => { return a.name.valueOf() > b.name.valueOf() });
                      // Remove the setting from the "set" list
                      this.mutable_settings.splice(this.mutable_settings.findIndex(u=> u.id == setting.id),1);
                      this.form.inst_id = 0;
                  }
                })
                .catch({});
            },
            testSettings (event) {
                if (!(this.is_admin || this.is_manager)) { return; }
                this.showTest = true;
                this.testData = '';
                this.testStatus = "... Working ...";
                axios.get('/sushisettings-test'+'?prov_id='+this.form.prov_id+'&'
                                               +'requestor_id='+this.form.requestor_id+'&'
                                               +'customer_id='+this.form.customer_id+'&'
                                               +'apikey='+this.form.API_key)
                     .then((response) => {
                        if (response.data.result == '') {
                            this.testStatus = "No results!";
                        } else {
                            this.testStatus = response.data.result;
                            this.testData = response.data.rows;
                        }
                    })
                   .catch(error => {});
            },
            onUnsetChange (prov) {
                this.form.customer_id = '';
                this.form.requestor_id = '';
                this.form.API_key = '';
				this.showForm = true;
            },
            hideForm (event) {
                this.showForm = false;
			},
        },
        computed: {
          ...mapGetters(['is_admin','is_manager'])
        },
        mounted() {
            // Sort the settings by institution name
            this.mutable_settings.sort((a,b) => {
                return a.institution.name.valueOf() > b.institution.name.valueOf();
            });
            console.log('Institutions-by-Prov Component mounted.');
        }
    }
</script>

<style>
.good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
