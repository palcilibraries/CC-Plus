<template>
  <div>
    <span class="form-info" role="alert" v-text="message"></span>
    <form method="POST" action='/sushisettings-update' @submit.prevent="onSubmit"
          @keydown="form.errors.clear($event.target.name)">
      <input v-model="prov_id" type="hidden">
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12">
          <div class="form-group">
            <label for="prov_id">Institution:</label>
            <select name="inst_id" v-model="form.inst_id" @change="onInstChange">
              <option v-for="(inst, index) in institutions" :value="index">{{ inst }}</option>
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
      <button class="btn btn-primary" type="submit" :disabled="form.errors.any()">Update Sushi Settings</button>
    </form>
  </div>
</template>

<script>
    export default {
        props: {
                prov_id: { type:Number, default:0 },
                institutions: { type:Object, default: () => ({}) }
               },

        data() {
            return {
                message: '',
                form: new window.Form({
                    inst_id: '1',
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
                        self.form.inst_id=1;
                    });
            },
            onInstChange (event) {
                var self = this;
                axios.get('/sushisettings-refresh'+'?inst_id='+event.target.value+'&'+'prov_id='+this.prov_id)
                     .then( function(response) {
                         if ( response.data.settings.count === 0) {
                             self.message = 'No settings found for this institution - creating new entry.';
                             self.form.customer_id = '';
                             self.form.requestor_id = '';
                             self.form.API_key = '';
                         } else {
                            self.form.customer_id = response.data.settings.customer_id;
                            self.form.requestor_id = response.data.settings.requestor_id;
                            self.form.API_key = response.data.settings.API_key;
                            self.message = '';
                        }
                        console.log(response.data.settings);
                    })
                    .catch(error => {});
            }
        },

        mounted() {
            this.form.prov_id = this.prov_id;
            console.log('Component mounted.')
        }
    }
</script>

<style>
</style>
