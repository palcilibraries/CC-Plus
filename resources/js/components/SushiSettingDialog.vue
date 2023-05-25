<template>
  <div>
    <v-container grid-list-md>
      <v-form v-model="formValid" :key="'UFrm'+form_key">
        <v-row class="d-flex ma-2" no-gutters>
         <v-col v-if="dtype=='edit'" class="d-flex pt-4 justify-center"><h4 align="center">Edit Sushi Connection</h4></v-col>
         <v-col v-else class="d-flex pt-4 justify-center"><h4 align="center">Create Sushi Connection</h4></v-col>
        </v-row>
        <div v-if="sushi_inst.id==null || sushi_prov.id==null">
          <v-row class="d-flex ma-2 justify-center" no-gutters>
            <v-col v-if="institutions.length>1" class="d-flex px-2" cols="5">
              <v-select :items="institutions" v-model="sushi_inst" return-object item-text="name" item-value="id"
                        label="Choose an Institution"></v-select>
            </v-col>
            <v-col v-else class="d-flex px-2" cols="5"><strong>{{ sushi_inst.nanme}}</strong></v-col>
            <v-col cols="2" class="d-flex justify-center"><< -- >></v-col>
            <v-col v-if="providers.length>1" class="d-flex px-2" cols="5">
              <v-select :items="providers" v-model="sushi_prov" return-object item-text="name" item-value="id"
                        label="Choose a Provider"></v-select>
            </v-col>
            <v-col v-else class="d-flex px-2" cols="5"><strong>{{ sushi_prov.nanme}}</strong></v-col>
          </v-row>
        </div>
        <div v-else>
          <v-row class="d-flex ma-2 justify-center" no-gutters>
            <strong>{{ sushi_inst.name }} << -- >> {{ sushi_prov.name }}</strong>
          </v-row>
          <template v-for="cnx in sushi_prov.global_prov.connectors">
            <v-row class="d-flex mx-2" no-gutters>
              <v-col class="d-flex px-2" cols="8">
                <v-text-field v-model="form[cnx.name]" :label='cnx.label' :id='cnx.name' outlined :rules="[required]"
                ></v-text-field>
              </v-col>
            </v-row>
          </template>
          <div v-if="showTest">
            <div>{{ testStatus }}</div>
            <div v-for="row in testData">{{ row }}</div>
          </div>
        </div>
      </v-form>
    </v-container>
    <div v-if="success || failure" class="status-message">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <v-row class="d-flex ma-2" >
      <v-col v-if="sushi_inst.id!=null && sushi_prov.id!=null" class="d-flex px-2 justify-center">
        <v-btn small class='btn' color="primary" @click="saveSetting" :disabled="!formValid">Save</v-btn>
      </v-col>
      <v-col v-if="sushi_inst.id!=null && sushi_prov.id!=null" class="d-flex px-2 justify-center">
        <v-btn small color="secondary" type="button" @click="testSettings">Test Settings</v-btn>
      </v-col>
      <v-col class="d-flex px-2 justify-center">
        <v-btn small class='btn' type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
      </v-col>
    </v-row>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String, default: "create" },
            institutions: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
    },
    data () {
      return {
        success: '',
        failure: '',
        form_key: 1,
        formValid: true,
        showTest: false,
        sushi_inst: {},
        sushi_prov: {},
        testData: '',
        testStatus: '',
        form: new window.Form({
            inst_id: null,
            prov_id: null,
            global_id: null,
            customer_id: '',
            requestor_id: '',
            API_key: '',
            extra_args: '',
            status: 'Enabled'
        }),
      }
    },
    watch: {
      sushi_inst: function (inst) {
        this.form.reset();
        this.form.inst_id = inst.id;
      },
      sushi_prov: function (prov) {
        this.form.reset();
        this.form.prov_id = prov.id;
        this.form.global_id = prov.global_id;
      }
    },
    methods: {
      saveSetting (event) {
          this.success = '';
          this.failure = '';
          // All connectors are required - whether they work or not is a matter of testing+confirming
          this.sushi_prov.global_prov.connectors.forEach( (cnx) => {
              if (this.form[cnx.name] == '' || this.form[cnx.name] == null) {
                  this.failure = "Error: "+cnx.name+" must be supplied to connect to this provider!";
              }
          });
          if (this.failure != '') return;
          this.sushi_inst = { ...this.default_inst};
          this.sushi_prov = { ...this.default_prov};
          // Call create() method on sushisettings controller to add to the table
          this.form.post('/sushisettings')
            .then((response) => {
                if (response.result) {
                    this.$emit('sushi-done', { result:'Success', msg:null, setting:response.setting });
                } else {
                    this.$emit('sushi-done', { result:'Fail', msg:response.msg, setting:null });
                }
            });
      },
      cancelDialog () {
        this.success = '';
        this.failure = '';
        this.sushi_inst = { ...this.default_inst};
        this.sushi_prov = { ...this.default_prov};
        this.$emit('sushi-done', { result:'Cancel', msg:null, setting:null });
      },
      testSettings (event) {
          this.failure = '';
          this.success = '';
          this.testData = '';
          this.testStatus = "... Working ...";
          this.showTest = true;
          var testArgs = {'prov_id' : this.form.prov_id};
          if (this.sushi_prov.global_prov.connectors.some(c => c.name === 'requestor_id')) {
            testArgs['requestor_id'] = this.form.requestor_id;
          }
          if (this.sushi_prov.global_prov.connectors.some(c => c.name === 'customer_id')) {
            testArgs['customer_id'] = this.form.customer_id;
          }
          if (this.sushi_prov.global_prov.connectors.some(c => c.name === 'API_key')) {
            testArgs['API_key'] = this.form.API_key;
          }
          if (this.sushi_prov.global_prov.connectors.some(c => c.name === 'extra_args')) {
            testArgs['extra_args'] = this.form.extra_args;
          }
          axios.post('/sushisettings-test', testArgs)
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
      required: function (value) {
        return (value) ? true : 'required field';
      },
    },
    computed: {
      ...mapGetters(['is_manager','is_admin']),
      default_inst: function () {
        return (this.institutions.length == 1) ? { ...this.institutions[0] } : {id: null};
      },
      default_prov: function () {
        return (this.providers.length == 1) ? { ...this.providers[0] }
                                            : { id: null, global_id: null, global_prov: {connectors: []} };
      }
    },
    mounted() {
      this.sushi_inst = { ...this.default_inst};
      this.sushi_prov = { ...this.default_prov};
      console.log('SushiDialog Component mounted.');
    }
  }
</script>
<style>
</style>
