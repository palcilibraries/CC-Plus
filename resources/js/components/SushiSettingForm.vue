<template>
  <div class="details">
  	<v-row no-gutters>
	  <h2 class="section-title">Sushi Settings</h2>
      <v-col class="d-flex ma-2" cols="2" sm="2">
        <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
      </v-col>
      <v-col class="d-flex ma-2" cols="2" sm="2">
        <v-btn small class='btn btn-danger' type="button" @click="destroy(setting.id)">Delete</v-btn>
      </v-col>
	</v-row>
    <div v-if="!showForm">
      <!-- form display control and confirmations  -->
      <!-- Values-only when form not active -->
      <v-row>
        <v-col v-if="setting.provider.connectors.some(c => c.name === 'customer_id')" cols="3">
          <strong>Customer ID: </strong>{{ form.customer_id }}
        </v-col>
      	<v-col v-if="setting.provider.connectors.some(c => c.name === 'requestor_id')" cols="3">
          <strong>Requestor ID: </strong>{{ form.requestor_id }}
        </v-col>
      	<v-col v-if="setting.provider.connectors.some(c => c.name === 'API_key')" cols="3">
          <strong>API Key: </strong>{{ form.API_key }}
        </v-col>
        <v-col v-if="setting.provider.connectors.some(c => c.name === 'extra_args')" cols="3">
          <strong>Extra Args: </strong>{{ form.extra_args }}
        </v-col>
      </v-row>
      <v-row>
        <v-col cols="12">
          <strong>Support Email: </strong><a :href="'mailto:'+form.support_email">{{ form.support_email }}</a>
        </v-col>
      </v-row>
      <v-row>
        <v-col v-if="form.status == 'Enabled'" cols="4"><strong><font color='green'>Harvesting Enabled</font></strong></v-col>
        <v-col v-else  cols="4"><strong><font color='red'>Harvesting {{ form.status }}</font></strong></v-col>
        <v-col v-if="setting.next_harvest" cols="8">
          <strong>Next Harvest: </strong>{{ setting.next_harvest }}</a>
        </v-col>
      </v-row>
      <div>
	  	  <h2>Actions</h2>
          <v-btn small color="secondary" type="button" @click="testSettings"
                 style="display:inline-block;margin-right:1em;">test</v-btn>
          <v-btn v-if="form.status == 'Enabled'"  small color="secondary" type="button" @click="changeStatus('Suspended')"
                 style="display:inline-block;margin-right:1em;">suspend</v-btn>
          <v-btn v-else small color="green" type="button" @click="changeStatus('Enabled')"
                 style="display:inline-block;margin-right:1em;">enable</v-btn>
          <a :href="'/harvestlogs/create?inst='+setting.inst_id+'&prov='+setting.prov_id">
            <v-btn small color="primary" type="button" style="display:inline-block;margin-right:1em;">harvest</v-btn>
          </a>
      </div>
      <v-row v-if="showTest || success || failure" class="status-message">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        <div v-if="showTest">
          <div>{{ testStatus }}</div>
          <div v-for="row in testData">{{ row }}</div>
        </div>
      </v-row>
    </div>

    <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
    <div v-else>
      <v-row>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'customer_id')">
            <v-text-field v-model="form.customer_id" label="Customer ID" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'requestor_id')">
            <v-text-field v-model="form.requestor_id" label="Requestor ID" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'API_key')">
            <v-text-field v-model="form.API_key" label="API_key" outlined></v-text-field>
          </v-col>
          <v-col v-if="setting.provider.connectors.some(c => c.name === 'extra_args')">
            <v-text-field v-model="form.extra_args" label="Extra Arguments" outlined></v-text-field>
          </v-col>
          <v-col>
            <v-text-field v-model="form.support_email" label="Support Email" outlined></v-text-field>
          </v-col>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
            Save Settings
          </v-btn>
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
      </v-row>
    </div>
  </div>
</template>

<script>
    import Form from '@/js/plugins/Form';
    import Swal from 'sweetalert2';
    window.Form = Form;
    export default {
        props: {
                setting: { type:Object, default: () => {} },
               },
        data() {
            return {
                success: '',
                failure: '',
                status: '',
				showForm: false,
                showTest: false,
                testData: '',
                testStatus: '',
                form: new window.Form({
                    customer_id: this.setting.customer_id,
                    requestor_id: this.setting.requestor_id,
                    API_key: this.setting.API_key,
                    extra_args: this.setting.extra_args,
                    support_email: this.setting.support_email,
                    inst_id: this.setting.inst_id,
                    prov_id: this.setting.prov_id,
                    status: this.setting.status,
                })
            }
        },
        methods: {
            formSubmit (event) {
	            this.form.post('/sushisettings-update')
                    .then( (response) => {
	                    this.warning = '';
	                    this.confirm = 'Settings successfully updated.';
	                });
                this.showForm = false;
            },
            swapForm (event) {
                this.showForm = true;
            },
            hideForm (event) {
                this.showForm = false;
            },
            destroy (settingid) {
                var self = this;
                let message = "Deleting these settings cannot be reversed, only manually recreated.";
                message += " NOTE: Harvest Log and Failed Harvest records connected to these settings";
                message += " will also be deleted!";
                Swal.fire({
                  title: 'Are you sure?',
                  text: message,
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
                                   self.form.customer_id = '';
                                   self.form.requestor_id = '';
                                   self.form.API_key = '';
                                   self.form.extra_args = '';
                                   self.form.support_email = '';
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
            changeStatus (new_status) {
                axios.post('/sushisettings-update', {
                    inst_id: this.setting.inst_id,
                    prov_id: this.setting.prov_id,
                    status: new_status
                })
                .then( (response) => {
                    if (response.data.result) {
                        this.form.status = new_status;
                    } else {
                        self.success = '';
                        self.failure = response.data.msg;
                    }
                })
                .catch(error => {});
            },
            testSettings (event) {
                var self = this;
                self.showTest = true;
                self.testData = '';
                self.testStatus = "... Working ...";
                var testArgs = {'prov_id' : this.form.prov_id};
                if (this.connectors.some(c => c.name === 'requestor_id')) testArgs['requestor_id'] = this.form.requestor_id;
                if (this.connectors.some(c => c.name === 'customer_id')) testArgs['customer_id'] = this.form.customer_id;
                if (this.connectors.some(c => c.name === 'API_key')) testArgs['API_key'] = this.form.API_key;
                if (this.connectors.some(c => c.name === 'extra_args')) testArgs['extra_args'] = this.form.extra_args;
                axios.post('/sushisettings-test', testArgs)
                     .then((response) => {
                        if ( response.data.result == '') {
                            self.testStatus = "No results!";
                        } else {
                            self.testStatus = response.data.result;
                            self.testData = response.data.rows;
                        }
                    })
                   .catch(error => {});
            },
        },
        mounted() {
            this.showForm = false;
            console.log('SushiSettingForm Component mounted.');
        }
    }
</script>

<style>

</style>
