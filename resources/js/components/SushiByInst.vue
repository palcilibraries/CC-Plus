<template>
  <div>
      <span class="form-info" role="alert" v-text="warning"></span>
      <span class="form-good" role="alert" v-text="confirm"></span>
    <v-app sushiform>
        <form method="POST" action='/sushisettings-update' @submit.prevent="formSubmit"
              @keydown="form.errors.clear($event.target.name)">
            <input v-model="prov_id" type="hidden">
            <v-container grid-list-xl>
                <v-row align="center">
                    <v-col class="d-flex" cols="12" sm="6">
                        <v-select
                            :items="institutions"
                            v-model="form.inst_id"
                            @change="onInstChange"
                            label="Institution"
                            placeholder="Choose an Institution"
                            item-text="name"
                            item-value="id"
                            outlined
                        ></v-select>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col class="d-flex" cols="12" sm="6">
                        <v-text-field v-model="form.customer_id"
                                      label="Customer ID"
                                      id="customer_id"
                                      outlined
                        ></v-text-field>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col class="d-flex" cols="12" sm="6">
                        <v-text-field v-model="form.requestor_id"
                                      label="Requestor ID"
                                      id="requestor_id"
                                      outlined
                        ></v-text-field>
                    </v-col>
                </v-row>
                <v-row>
                    <v-col class="d-flex" cols="12" sm="6">
                        <v-text-field v-model="form.API_key"
                                      label="API Key"
                                      id="API_key"
                                      outlined
                        ></v-text-field>
                    </v-col>
                </v-row>
                <v-row>
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
        </form>
    </v-app>
  </div>
</template>

<script>
    export default {
        props: {
                prov_id: { type:Number, default:0 },
                institutions: { type:Array, default: () => [] },
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
            onInstChange (inst) {
                var self = this;
                self.showTest = false;
                axios.get('/sushisettings-refresh'+'?inst_id='+inst+'&'+'prov_id='+this.prov_id)
                     .then( function(response) {
                         if ( response.data.settings.count === 0) {
                             self.warning = 'No settings found for this institution - creating new entry.';
                             self.confirm = '';
                             self.form.customer_id = '';
                             self.form.requestor_id = '';
                             self.form.API_key = '';
                             self.allowTest = false;
                         } else {
                             self.form.customer_id = response.data.settings.customer_id;
                             self.form.requestor_id = response.data.settings.requestor_id;
                             self.form.API_key = response.data.settings.API_key;
                             self.warning = '';
                             self.confirm = '';
                             self.allowTest = true;
                         }
                     })
                     .catch(error => {});
            },
            testSettings (event) {
                var self = this;
                self.showTest = true;
                self.testData = '';
                self.testStatus = "... Working ...";
                axios.get('/sushisettings-test'+'?prov_id='+this.prov_id+'&'+'inst_id='+self.form.inst_id)
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

        mounted() {
            this.form.prov_id = this.prov_id;
            console.log('Sushi-by-Inst Component mounted.')
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
