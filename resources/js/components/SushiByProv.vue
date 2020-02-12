<template>
  <div>
    <span class="form-info" role="alert" v-text="message"></span>
    <form method="POST" action="/sushisettings-update" @submit.prevent="formSubmit"
          @keydown="form.errors.clear($event.target.name)">
      <input v-model="inst_id" type="hidden">
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
          <div class="form-group">
            <label for="prov_id">Provider:</label>
            <select name="prov_id" v-model="form.prov_id" @change="onProvChange">
              <option v-for="(prov, index) in providers" :value="index">{{ prov }}</option>
            </select>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
          <div class="form-group">
            <label for="customer_id">Customer ID:</label>
            <input v-model="form.customer_id" type="text" id="customer_id">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
          <div class="form-group">
            <label for="requestor_id">Requestor ID:</label>
            <input v-model="form.requestor_id" type="text" id="requestor_id">
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
          <div class="form-group">
            <label for="API_key">API Key:</label>
            <input v-model="form.API_key" type="text" id="API_key">
          </div>
        </div>
      </div>
      <button class="btn btn-primary" type="submit" :disabled="form.errors.any()">Save Sushi Settings</button>
      <button class="btn btn-primary" type="button" @click="testSettings" v-if="allowTest">Test Settings</button>
    </form>
    <div v-if="showTest">{{ testStatus }}</div>
    <div v-for="row in testData" v-if="showTest">
        {{ row }}
    </div>
  </div>
</template>

<script>
    export default {
        props: {
                inst_id: { type:Number, default:0 },
                providers: { type:Array, default: () => [] },
               },

        data() {
            return {
                message: '',
                testData: '',
                testStatus: '',
                allowTest: false,
                showTest: false,
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
                        self.form.prov_id=0;
                        self.message = '';
                        self.form.customer_id = '';
                        self.form.requestor_id = '';
                        self.form.API_key = '';
                    });
            },
            onProvChange (event) {
                var self = this;
                self.showTest = false;
                axios.get('/sushisettings-refresh'+'?prov_id='+event.target.value+'&'+'inst_id='+this.inst_id)
                     .then( function(response) {
                         if ( response.data.settings.count === 0) {
                             self.message = 'No settings found for this provider - creating new entry.';
                             self.form.customer_id = '';
                             self.form.requestor_id = '';
                             self.form.API_key = '';
                             self.allowTest = false;
                         } else {
                             self.form.customer_id = response.data.settings.customer_id;
                             self.form.requestor_id = response.data.settings.requestor_id;
                             self.form.API_key = response.data.settings.API_key;
                             self.message = '';
                             self.allowTest = true;
                         }
                         console.log(response.data.settings);
                     })
                     .catch(error => {});
            },
            testSettings (event) {
                var self = this;
                self.showTest = true;
                self.testData = '';
                self.testStatus = "... Working ...";
                axios.get('/sushisettings-test'+'?prov_id='+self.form.prov_id+'&'+'inst_id='+this.inst_id)
                     .then( function(response) {
                        if ( response.data.result == '') {
                            self.testStatus = "No results!";
                        } else {
                            self.testStatus = response.data.result;
                            self.testData = response.data.rows;
                        }
                        console.log(response.data.result);
                    })
                   .catch(error => {});
            }
        },

        mounted() {
            this.form.inst_id = this.inst_id;
            console.log('Component mounted.')
        }
    }
</script>

<style>
.form-info {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
