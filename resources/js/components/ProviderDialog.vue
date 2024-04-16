<template>
  <div>
    <v-form v-model="formValid">
      <v-row class="d-flex ma-2" no-gutters>
        <v-col v-if="dtype=='edit'" class="d-flex pt-4 justify-center"><h1 align="center">Edit Provider settings</h1></v-col>
        <v-col v-else class="d-flex pt-4 justify-center"><h1 align="center">Connect a Provider</h1></v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-2" cols="8">
          <v-text-field v-model="form.name" label="Name" outlined dense></v-text-field>
        </v-col>
        <v-col v-if="dtype=='edit'" class="d-flex px-2" cols="2">
          <div class="idbox">
            <v-icon title="CC+ Provider ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ provider.conso_id }}
          </div>
        </v-col>
        <v-col v-if="is_globaladmin" class="d-flex px-2" cols="2">
          <div class="idbox">
            <v-icon title="CC+ Provider ID (Global)">mdi-web</v-icon>&nbsp; {{ provider.id }}
          </div>
        </v-col>
      </v-row>
      <v-row v-if="institutions.length>0" class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-2" cols="8">
          <v-autocomplete :items="institutions" v-model="form.inst_id" label="Serves" item-text="name" item-value="id"
                          outlined dense hint="Assign the provider to an institution" persistent-hint :rules="instRules"
                          :readonly="dtype=='edit' || !is_admin"
          ></v-autocomplete>
        </v-col>
        <v-col class="d-flex px-2" cols="2">
          <div class="idbox">
            <v-icon title="CC+ Institution ID">mdi-crosshairs-gps</v-icon>&nbsp; {{ form.inst_id }}
          </div>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-2" cols="3">
          <v-switch v-model="form.is_active" dense label="Active?"></v-switch>
        </v-col>
        <v-col v-if="is_admin && form.inst_id==1" class="d-flex px-2" cols="9">
          <v-switch v-model="allow_sushi" label="Allow Local Admins to Modify Credentials" @change="changeRestricted" dense
          ></v-switch>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="is_admin && dtype!='connect'" class="d-flex px-2">
          <v-switch v-model="form.allow_inst_specific" dense label="Allow Local Admins To Add An Institution-Specific Copy"
          ></v-switch>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="provider.master_reports.length>0" class="d-flex px-6 justify-center" cols="6">
          <strong>Reports to Harvest</strong>
        </v-col>
        <v-col v-else class="d-flex px-4 justify-center" cols="6">
          <strong>No reports enabled globally</strong>
        </v-col>
        <v-col class="d-flex px-6" cols="6"><strong>Run Harvests Monthly on Day</strong></v-col>
      </v-row>
      <v-row class="d-flex mx-2 my-0" no-gutters>
        <v-col v-if="provider.master_reports.length>0" class="d-flex px-4 justify-center" cols="6">
          <v-list class="shaded" dense>
            <v-list-item v-for="rpt in provider.master_reports" :key="rpt.name" class="verydense">
              <v-checkbox v-model="form.report_state[rpt.name]" :key="rpt.name" :label="rpt.name" dense
              ></v-checkbox>
            </v-list-item>
          </v-list>
          <div class="float-none"></div>
        </v-col>
        <v-col v-else class="d-flex" cols="6">&nbsp;</v-col>
        <v-col class="d-flex pl-8" cols="2">
          <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line dense type="number"
                        class="centered-input" :rules="dayRules"
          ></v-text-field><br />
        </v-col>
      </v-row>
      <v-row v-if="provider.last_harvest!=null" class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-6" cols="6">&nbsp;</v-col>
        <v-col class="d-flex px-6" cols="6"><strong>Last Successful Harvest</strong></v-col>
      </v-row>
      <v-row v-if="provider.last_harvest!=null" class="d-flex mx-2 my-0" no-gutters>
        <v-col class="d-flex px-6" cols="6">&nbsp;</v-col>
        <v-col class="d-flex px-6" cols="6">{{ provider.last_harvest }}</v-col>
      </v-row>
      <v-row class="d-flex ma-2" no-gutters>
        <v-spacer></v-spacer>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn x-small color="primary" @click="saveProv" :disabled="!formValid">Save Provider</v-btn>
        </v-col>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn x-small color="primary" @click="cancelDialog">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-form>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String, default: "connect" },
            provider: { type:Object, default: () => {} },
            institutions: { type:Array, default: () => [] },
            // master_reports: { type:Array, default: () => [] },
           },
    data () {
      return {
        formValid: true,
        dayRules: [
            v => !!v || "Day of month is required",
            v => ( v && v >= 1 ) || "Day of month must be > 1",
            v => ( v && v <= 28 ) || "Day of month must be < 29",
        ],
        allow_sushi: 0,
        form: new window.Form({
            name: '',
            inst_id: 1,
            global_id: null,
            day_of_month: 15,
            is_active: 1,
            restricted: 0,
            allow_inst_specific: 0,
            sushi_stub: 1,
            report_state: {'DR': false, 'IR': false, 'PR': false, 'TR': false},
        }),
      }
    },
    methods: {
      saveProv (event) {
          if (this.dtype == 'edit') {
            this.form.patch('/providers/'+this.provider['conso_id'])
                .then( (response) => {
                    var _prov   = (response.result) ? response.provider : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.form.reset();
                    this.$emit('prov-complete', { result:_result, msg:response.msg, prov:_prov });
            });
          } else {
            this.form.post('/providers/connect')
                .then( (response) => {
                    var _prov   = (response.result) ? response.provider : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.form.reset();
                    this.$emit('prov-complete', { result:_result, msg:response.msg, prov:_prov });
                });
          }
      },
      cancelDialog () {
        this.form.reset();
        this.$emit('prov-complete', { result:'Cancel', msg:null, prov:null });
      },
      changeRestricted () {
        this.form.restricted = (this.allow_sushi==1) ? 0 : 1;
      },
    },
    computed: {
      ...mapGetters(['is_admin','is_globaladmin']),
      instRules() {
          if (this.dtype == 'connect') {
              return [ v => !!v || 'Institution is required' ];
          } else {
              return [];
          }
      },
    },
    mounted() {
      this.form.name = this.provider.name;
      this.form.global_id = this.provider.id;
      this.form.inst_id = this.provider.inst_id;
      this.form.day_of_month = this.provider.day_of_month;
      this.form.is_active = this.provider.is_active;
      this.allow_sushi = (this.provider.restricted==1) ? 0 : 1;
      this.form.restricted = this.provider.restricted;
      this.form.allow_inst_specific = this.provider.allow_inst_specific;
      this.form.report_state = this.provider.report_state;

      console.log('ProviderDialog Component mounted.');
    }
  }
</script>
<style scoped>
.verydense {
  max-height: 16px;
}
</style>
