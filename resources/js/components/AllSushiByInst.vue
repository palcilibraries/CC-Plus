<template>
  <div>
  <template v-if="is_manager || is_admin">
	  <form method="POST" action="/sushisettings-update" @submit.prevent="formSubmit"
	        @keydown="form.errors.clear($event.target.name)">
	    <v-select
	          :items="unset"
			  v-model="form.prov_id"
	          @change="onUnsetChange"
	          placeholder="Connect a Provider"
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
			<v-btn small type="button" @click="hideForm">cancel</v-btn>
		</div>
	  </form>
	  	</template>
    <v-data-table :headers="headers" :items="mutable_settings" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td><a :href="'/providers/'+item.provider.id+'/edit'">{{ item.provider.name }}</a></td>
          <td>{{ item.customer_id }}</td>
          <td>{{ item.requestor_id }}</td>
          <td>{{ item.API_key }}</td>
          <td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete connection</v-btn></td>
		  <td><v-btn class='btn' small type="button">Settings & harvests</v-btn></td>
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
                settings: { type:Array, default: () => {} },
                unset: { type:Array, default: () => {} },
               },
        data() {
            return {
                success: '',
                failure: '',
				showForm: false,
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
                    prov_id: '0',
                    customer_id: '',
                    requestor_id: '',
                    API_key: ''
				})
            }
        },
        methods: {
	        formSubmit (event) {
	            var self = this;
	            this.form.post('/sushisettings-update')
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
            onUnsetChange (prov) {
				console.log(prov);
				//console.log(form);
				console.log(this.showForm);
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
            console.log('Providers-by-Inst Component mounted.');
			//console.log(form);
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
