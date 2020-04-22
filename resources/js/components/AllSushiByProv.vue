<template>
  <div>
  <template v-if="is_manager || is_admin">
	  <form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
	        @keydown="form.errors.clear($event.target.name)">
        <input v-model="form.prov_id" id="prov_id" type="hidden">
	    <v-select
	          :items="unset"
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
    <v-data-table :headers="headers" :items="mutable_settings" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td><a :href="'/institutions/'+item.institution.id">{{ item.institution.name }}</a></td>
          <td>{{ item.customer_id }}</td>
          <td>{{ item.requestor_id }}</td>
          <td>{{ item.API_key }}</td>
          <td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete connection</v-btn></td>
          <td>
            <v-btn class='btn' small type="button" :href="'/sushisettings/'+item.id+'/edit'">Settings & harvests</v-btn>
          </td>
        </tr>
      </template>
      <tr><td colspan="6">&nbsp;</td></tr>
    </v-data-table>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
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
				showForm: false,
                showTest: false,
                testData: '',
                testStatus: '',
                mutable_settings: this.settings,
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
	            var self = this;
	            // this.form.post('/sushisettings-update')
                this.form.post('/sushisettings')
	                .then( function(response) {
	                    self.warning = '';
	                    self.confirm = 'Settings successfully updated.';
	                });
	        },
            destroy (settingid) {
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
                      axios.delete('/sushisettings/'+settingid)
                           .then( (response) => {
                               if (response.data.result) {
                                   self.failure = '';
                                   self.success = response.data.msg;
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                       this.mutable_settings.splice(this.mutable_settings.findIndex(u=> u.id == userid),1);
                  }
                })
                .catch({});
            },
            testSettings (event) {
                if (!(this.is_admin || this.is_manager)) { return; }
                var self = this;
                self.showTest = true;
                self.testData = '';
                self.testStatus = "... Working ...";
                // axios.get('/sushisettings-test'+'?prov_id='+self.form.prov_id+'&'+'inst_id='+this.inst_id)
                axios.get('/sushisettings-test'+'?prov_id='+self.form.prov_id+'&'
                                               +'requestor_id='+this.form.requestor_id+'&'
                                               +'customer_id='+this.form.customer_id+'&'
                                               +'apikey='+this.form.API_key)
                     .then( function(response) {
                        if ( response.data.result == '') {
                            self.testStatus = "No results!";
                        } else {
                            self.testStatus = response.data.result;
                            self.testData = response.data.rows;
                        }
                    })
                   .catch(error => {});
            },
            onUnsetChange (prov) {
				// console.log(this.showForm);
				this.showForm = true;
            },
            hideForm (event) {
                var self = this;
                self.showForm = false;
			},
        },
        computed: {
          ...mapGetters(['is_admin','is_manager'])
        },
        mounted() {
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
