<template>
  <div>
    <form method="POST" action="" @submit.prevent="formSubmit"
          @keydown="form.errors.clear($event.target.name)">
      <input v-model="inst_id" id="inst_id" type="hidden">
      <v-container grid-list-xl>
        <v-row align="center">
          <v-col class="d-flex" cols="12" sm="6">
            <v-select
                :items="providers"
                v-model="form.prov_id"
                @change="onProvChange"
                label="Provider"
                placeholder="Choose a Provider"
                item-text="name"
                item-value="id"
                outlined
            ></v-select>
          </v-col>
        </v-row>
        <v-row v-if="is_manager || is_admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-text-field v-model="form.customer_id"
                          label="Customer ID"
                          id="customer_id"
                          outlined
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row v-else>
          <v-col class="d-flex" cols="2">Customer ID</v-col>
          <v-col><div v-text="form.customer_id"></div></v-col>
        </v-row>
        <v-row v-if="is_manager || is_admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-text-field v-model="form.requestor_id"
                          label="Requestor ID"
                          id="requestor_id"
                          outlined
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row v-else>
          <v-col class="d-flex" cols="2">Requestor ID</v-col>
          <v-col><div v-text="form.requestor_id"></div></v-col>
        </v-row>
        <v-row v-if="is_manager || is_admin">
          <v-col class="d-flex" cols="12" sm="6">
            <v-text-field v-model="form.API_key"
                          label="API Key"
                          id="API_key"
                          outlined
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row v-else>
          <v-col class="d-flex" cols="2">API Key</v-col>
          <v-col><div v-text="form.API_key"></div></v-col>
        </v-row>
        <v-row v-if="is_manager || is_admin">
          <v-flex md3>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save Sushi Settings</v-btn>
          </v-flex>
        </v-row>
        <v-row v-if="allowTest">
          <v-flex md3>
            <br />
            <v-btn small color="primary" type="button" @click="testSettings">Test Settings</v-btn>
            <br />
          </v-flex>
        </v-row>
        <v-row v-if="showTest">
          <div>{{ testStatus }}</div>
          <div v-for="row in testData">{{ row }}</div>
        </v-row>
      </v-container>
      <span class="form-info" role="alert" v-text="warning"></span>
      <span class="form-good" role="alert" v-text="confirm"></span>
    </form>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Form from '@/js/plugins/Form';
    import axios from 'axios';
    window.Form = Form;
    // window.axios = axios;

    export default {
        props: {
                inst_id: { type:Number, default:0 },
                providers: { type:Array, default: () => [] },
               },
        data() {
            return {
                warning: '',
                confirm: '',
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
                        self.warning = '';
                        self.confirm = 'Settings successfully updated.';
                    });
            },
            onProvChange (prov) {
                this.showTest = false;
                var self = this;
                axios.get('/sushisettings-refresh'+'?prov_id='+prov+'&'+'inst_id='+this.inst_id)
                     .then( function(response) {
                         if ( response.data.settings.count === 0) {
                             self.confirm = '';
                             self.allowTest = false;
                             self.warning = 'No settings found for this provider';
                             if (self.admin || self.is_manager) {
                                 self.warning += ' - creating new entry.';
                             }
                             self.form.customer_id = '';
                             self.form.requestor_id = '';
                             self.form.API_key = '';
                         } else {
                             self.form.customer_id = response.data.settings.customer_id;
                             self.form.requestor_id = response.data.settings.requestor_id;
                             self.form.API_key = response.data.settings.API_key;
                             self.warning = '';
                             self.confirm = '';
                             if (self.is_admin || self.is_manager) {
                                 self.allowTest = true;
                             } else {
                                 self.allowTest = false;
                             }
                         }
                     })
                     .catch(error => {});
            },
            testSettings (event) {
                if (!(this.is_admin || this.is_manager)) { return; }
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
                    })
                   .catch(error => {});
            }
        },
        computed: {
          ...mapGetters(['is_admin','is_manager'])
        },
        mounted() {
            this.form.inst_id = this.inst_id;
            console.log('Sushi-by-Prov Component mounted.')
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
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
</style>
