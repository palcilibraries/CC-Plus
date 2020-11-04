<template>
  <div>
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm">Reset Selections</v-btn>
    </div>
  	<form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
  	      @keydown="form.errors.clear($event.target.name)">
      <div v-if="this.is_admin">
        <v-row class="d-flex align-mid">
          <v-col v-if="form.inst_group_id==0" class="d-flex ma-2" cols="3" sm="3">
            <v-select
              :items="institutions"
              v-model="form.inst"
              @change="onInstChange"
              multiple
              label="Institution(s)"
              item-text="name"
              item-value="id"
              hint="Institution(s) to Harvest"
            ></v-select>
          </v-col>
          <v-col v-if="form.inst.length==0 && form.inst_group_id==0 " class="d-flex" cols="1" sm="1">
            <strong>OR</strong>
          </v-col>
          <v-col v-if="form.inst.length==0" class="d-flex ma-2" cols="3" sm="3">
            <v-select
                :items="inst_groups"
                v-model="form.inst_group_id"
                @change="onGroupChange"
                label="Institution Group"
                item-text="name"
                item-value="id"
                hint="Institution group to harvest"
            ></v-select>
          </v-col>
        </v-row>
      </div>
      <v-row v-else>
        <v-col class="ma-2" cols="6" sm="4">
          <h5>Institution : {{ inst_name }}</h5>
        </v-col>
      </v-row>
      <v-row v-if="available_providers.length>0">
        <v-col class="ma-2" cols="3" sm="3">
          <v-select
            :items="available_providers"
            v-model="form.prov"
            @change="onProvChange"
            multiple
            label="Provider(s)"
            item-text="name"
            item-value="id"
            hint="Provider(s) to Harvest"
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="available_reports.length>0">
        <v-col class="ma-2" cols="6" sm="4">
          <v-select
            :items="available_reports"
            v-model="form.reports"
            item-text="legend"
            item-value="name"
            label="Report(s) to Harvest"
            multiple
            chips
            hint="Choose which master reports to harvest"
            persistent-hint
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex flex-row ma-2 align-center">
        <v-col class="d-flex pa-2" cols="2" sm="2"><h5>Month(s) to Harvest</h5></v-col>
        <v-col class="d-flex pa-2">
          <date-range minym="2019-01" :maxym="maxYM" ymfrom="" ymto=""></date-range>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0">
        <v-col class="ma-2" cols="12">
          <span>Queue the harvest(s) to begin</span>
          <v-radio-group v-model="form.when" row>
            <v-radio :label="'Overnight'" value='later'></v-radio>
            <v-radio :label="'Now'" value='now'></v-radio>
          </v-radio-group>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0">
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Submit</v-btn>
      </v-row>
      <v-row v-else-if="form.inst.length>0 && form.prov.length>0 && available_reports.length==0">
        <span class="form-fail" role="alert">No reports defined or available for selected Provider/Institution.</span>
      </v-row>
    </form>
    <div>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </div>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            inst_groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            all_reports: { type:Array, default: () => [] },
            presets: { type:Object, default: () => {} },
    },
    data() {
        return {
            success: '',
            failure: '',
            selections_made: false,
            form: new window.Form({
                inst: [],
                inst_group_id: 0,
                prov: [],
                reports: [],
                fromYM: '',
                toYM: '',
                when: 'later',
            }),
            maxYM: '',
            inst_name: '',
            available_providers: this.providers,
            available_reports: [],
        }
    },
    methods: {
        resetForm () {
            // Reset form values
            this.form.inst = [];
            this.form.prov = [];
            this.form.inst_group_id = 0;
            this.form.reports = [];
            this.form.fromYM = '';
            this.form.toYM = '';
            this.selections_made = false;
            this.available_providers = [];
            this.available_reports = [];
        },
        // Verify provider preset value
        verifyProvPreset() {
            let preset_id = Number(this.presets['prov_id']);
            let prov = this.available_providers.find(p => p.id == preset_id);
            if (prov) {
                this.form.prov = [preset_id];
                this.onProvChange([preset_id]);
            } else {
                this.failure = 'The preset provider is not available - verify sushi settings';
                this.form.prov = [];
                this.presets['prov_id'] = null;
            }
        },
        // Update mutable providers when inst-group changes
        onGroupChange(groupid) {
            if (groupid == 0) {
                this.available_providers = this.providers;
            } else {
                this.updateProviders();
            }
            if (this.presets['prov_id']) this.verifyProvPreset();
            this.selections_made = true;
        },
        // Update mutable providers when inst changes
        onInstChange(inst_list) {
            if (inst_list.length == 0) {
                this.available_providers = this.providers;
            } else {
                this.updateProviders();
            }
            if (this.presets['prov_id']) this.verifyProvPreset();
            this.selections_made = true;
        },
        // External axios call to return available providers
        updateProviders () {
            let inst_ids = JSON.stringify(this.form.inst);
            axios.get('/available-providers?inst_ids='+inst_ids+'&group_id='+this.form.inst_group_id)
                 .then((response) => {
                     this.available_providers = response.data.providers;
                 })
                 .catch(error => {});
        },
        onProvChange(prov_list) {
            this.failure = '';
            // If no providers, set to available to all
            if (prov_list.length == 0) {
                this.available_reports = this.all_reports;
            // Update available reports when providers changes
            } else {
                this.available_reports = [];
                prov_list.forEach(pid => {
                    let cur_prov = this.providers.find(p => p.id == pid);
                    if (typeof(cur_prov.reports) != 'undefined') {
                        cur_prov.reports.forEach(report =>{
                            if (!this.available_reports.some(elem => elem.id === report.id)) {
                                this.available_reports.push(report);
                            }
                        });
                    }
                });
            }
            this.selections_made = true;
        },
        formSubmit (event) {
            if (this.form.reports.length == 0) {
                this.failure = 'No reports selected for harvesting';
                return;
            }
            // Set from/to in the form with values from the data store and check them
            this.form.toYM = this.filter_by_toYM;
            this.form.fromYM = this.filter_by_fromYM;
            if (this.form.toYM == '' && this.form.fromYM != '') this.form.toYM = this.form.fromYM;
            if (this.form.fromYM == '' && this.form.toYM != '') this.form.fromYM = this.form.toYM;
            if (this.form.toYM == '' || this.form.fromYM == '') {
                this.failure = 'Range of months to harvest is invalid';
                return;
            }
            this.form.post('/harvestlogs')
                .then((response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'filter_by_toYM', 'filter_by_fromYM']),
    },
    mounted() {
      if ( !this.is_admin ) {
          this.form.inst = [this.institutions[0].id];
          this.inst_name = this.institutions[0].name;
          this.onInstChange(this.form.inst);
      }
      let dt = new Date();
      this.maxYM = dt.getFullYear() + '-' + ('0' + (dt.getMonth()+1)).slice(-2);

      // Apply inbound institution preset (provider handled in the InstChange function)
      if (this.presets['inst_id']) {
          let instid = Number(this.presets['inst_id']);
          this.form.inst = [instid];
          this.onInstChange([instid]);
      }

      console.log('ManualHarvest Component mounted.');
    }
  }
</script>

<style>
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
