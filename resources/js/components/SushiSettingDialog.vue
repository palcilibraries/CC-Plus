<template>
  <div>
    <v-container grid-list-md>
      <v-form v-model="formValid" :key="'UFrm'+form_key">
        <v-row class="d-flex ma-2" no-gutters>
         <v-col v-if="mutable_dtype=='edit'" class="d-flex pt-4 justify-center"><h4 align="center">Edit Sushi Connection</h4></v-col>
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
          <template v-for="cnx in sushi_prov.connectors">
            <v-row class="d-flex mx-2" no-gutters>
              <v-col class="d-flex px-2" cols="8">
                <v-text-field v-model="form[cnx.name]" :label='cnx.label' :id='cnx.name' outlined :rules="[required]"
                ></v-text-field>
              </v-col>
            </v-row>
          </template>
          <v-row class="d-flex ma-2" no-gutters>
            <v-col class="d-flex px-2" cols="4">
              <v-select :items="statuses" v-model="statusval" label="Status" :readonly="form.status=='Suspended'"
              ></v-select>
            </v-col>
          </v-row>
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
            setting: { type:Object, default: () => {} },
            all_settings: { type:Array, default: () => [] },
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
        setting_id: null,
        mutable_dtype: this.dtype,
        statuses: ['Enabled','Disabled','Suspended','Incomplete'],
        statusval: 'Enabled',
        form: new window.Form({
            inst_id: null,
            prov_id: null,
            global_id: null,
            customer_id: '',
            requestor_id: '',
            API_key: '',
            extra_args: '',
            status: ''
        }),
      }
    },
    watch: {
      sushi_inst: function (inst) {
        this.form.inst_id = this.sushi_inst.id;
        if (this.form.prov_id != null && this.form.inst_id != null) {
          this.testExisting();
          this.form_key += 1;
        }
      },
      sushi_prov: function (prov) {
        this.form.prov_id = this.sushi_prov.id;
        if (this.form.prov_id != null && this.form.inst_id != null) {
          this.testExisting();
          this.form_key += 1;
        }
      },
    },
    methods: {
      testExisting() {
        let setting = this.all_settings.find(s => (s.prov_id == this.form.prov_id && s.inst_id == this.form.inst_id));
        if (typeof(setting) != 'undefined') {
          this.form.customer_id = setting.customer_id;
          this.form.requestor_id = setting.requestor_id;
          this.form.API_key = setting.API_key;
          this.form.extra_args = setting.extra_args;
          this.form.status = setting.status;
          this.mutable_dtype = 'edit';
          this.setting_id = setting.id;
        }
      },
      saveSetting (event) {
          this.success = '';
          this.failure = '';
          this.form.inst_id = this.sushi_inst.id;
          this.form.prov_id = this.sushi_prov.id;
          this.form.status = this.statusval;
          // All connectors are required - whether they work or not is a matter of testing+confirming
          this.sushi_prov.connectors.forEach( (cnx) => {
              if (this.form[cnx.name] == '' || this.form[cnx.name] == null) {
                  this.failure = "Error: "+cnx.name+" must be supplied to connect to this provider!";
              }
          });
          if (this.failure != '') return;
          this.sushi_inst = { ...this.default_inst};
          this.sushi_prov = { ...this.default_prov};

          if (this.mutable_dtype == 'create') {
            // Call create() method on sushisettings controller to add to the table
            this.form.post('/sushisettings')
              .then((response) => {
                  if (response.result) {
                      this.$emit('sushi-done', { result:'Created', msg:response.msg, setting:response.setting });
                  } else {
                      this.$emit('sushi-done', { result:'Fail', msg:response.msg, setting:null });
                  }
              });
          } else {  // edit/update
            this.form.patch('/sushisettings/'+this.setting_id)
                .then((response) => {
                  if (response.result) {
                      this.$emit('sushi-done', { result:'Updated', msg:response.msg, setting:response.setting });
                  } else {
                      this.$emit('sushi-done', { result:'Fail', msg:response.msg, setting:null });
                  }
                });
          }
      },
      cancelDialog () {
        this.success = '';
        this.failure = '';
        this.sushi_inst = { ...this.default_inst};
        this.sushi_prov = { ...this.default_prov};
        this.form_key += 1;
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
      isEmpty(obj) {
        for (var i in obj) return false;
        return true;
      }
    },
    computed: {
      ...mapGetters(['is_manager','is_admin']),
      default_inst: function () {
        return (this.institutions.length == 1) ? { ...this.institutions[0] } : {id: null};
      },
      default_prov: function () {
        return (this.providers.length == 1) ? { ...this.providers[0] }
                                            : { id: null, global_id: null, global_prov: { id:null, connectors: []} };
      }
    },
    mounted() {
      if (this.dtype == 'edit' && this.isEmpty(this.setting)) this.mutable_dtype = 'create';
      if (this.mutable_dtype == 'edit') {
          this.setting_id = this.setting.id;
          this.form.inst_id = this.setting.inst_id;
          this.form.prov_id = this.setting.prov_id;
          this.form.customer_id = this.setting.customer_id;
          this.form.requestor_id = this.setting.requestor_id;
          this.form.API_key = this.setting.API_key;
          this.form.extra_args = this.setting.extra_args;
          this.statusval = this.setting.status;
          this.sushi_prov = { ...this.setting.provider};
          this.sushi_inst = { ...this.setting.institution};
      } else {
          this.sushi_inst = { ...this.default_inst};
          this.sushi_prov = { ...this.default_prov};
      }
      console.log('SushiDialog Component mounted.');
    }
  }
</script>
<style>
</style>
