<template>
  <div>
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm">Reset Selections</v-btn>
    </div>
  	<form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
  	      @keydown="form.errors.clear($event.target.name)">
      <div v-if="this.is_admin">
        <v-row class="d-flex align-mid ma-2" no-gutters>
          <v-col v-if="form.inst_group_id==0" class="d-flex px-2" cols="3" sm="3">
            <v-autocomplete :items="institutions" v-model="form.inst" @change="onInstChange" multiple label="Institution(s)"
                            item-text="name" item-value="id" hint="Institution(s) to Harvest"
            ></v-autocomplete>
          </v-col>
          <v-col v-if="form.inst.length==0 && form.inst_group_id==0 " class="d-flex px-2" cols="1" sm="1">
            <strong>OR</strong>
          </v-col>
          <v-col v-if="form.inst.length==0" class="d-flex px-2" cols="3" sm="3">
            <v-autocomplete :items="inst_groups" v-model="form.inst_group_id" @change="onGroupChange" label="Institution Group"
                            item-text="name" item-value="id" hint="Institution group to harvest"
            ></v-autocomplete>
          </v-col>
        </v-row>
      </div>
      <div v-else>
        <v-row class="d-flex align-mid ma-2" no-gutters>
          <v-col class="d-flex px-2" cols="6" sm="4">
            <h5>Institution : {{ inst_name }}</h5>
          </v-col>
        </v-row>
      </div>
      <v-row v-if="available_providers.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="3" sm="3">
          <v-autocomplete :items="available_providers" v-model="form.prov" @change="onProvChange" multiple label="Provider(s)"
                          item-text="name" item-value="id" hint="Provider(s) to Harvest"
          ></v-autocomplete>
        </v-col>
      </v-row>
      <v-row v-if="available_reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="6" sm="4">
          <v-select :items="available_reports" v-model="form.reports" item-text="legend" item-value="name" multiple cjips
                    label="Report(s) to Harvest" hint="Choose which master reports to harvest" persistent-hint
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex flex-row ma-2 align-center" no-gutters>
        <v-col class="d-flex px-2" cols="2" sm="2"><h5>Month(s) to Harvest</h5></v-col>
        <v-col class="d-flex px-2" cols="1">
          <v-menu ref="menuF" v-model="fromMenu" :close-on-content-click="true" transition="scale-transition"
                  offset-y max-width="290px" min-width="290px">
            <template v-slot:activator="{ on }">
              <v-text-field v-model="form.fromYM" label="From" readonly v-on="on"></v-text-field>
            </template>
            <v-date-picker v-model="form.fromYM" type="month" :min="'2019-01'" no-title scrollable></v-date-picker>
          </v-menu>
        </v-col>
        <v-col class="d-flex px-2" cols="1">
          <v-menu ref="menuT" v-model="toMenu" :close-on-content-click="true" transition="scale-transition"
                  offset-y max-width="290px" min-width="290px">
            <template v-slot:activator="{ on }">
              <v-text-field v-model="form.toYM" label="To" readonly v-on="on"></v-text-field>
            </template>
            <v-date-picker v-model="form.toYM" type="month" :max="maxYM" no-title scrollable></v-date-picker>
          </v-menu>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="12">
          <span>Queue the harvest(s) to begin</span>
          <v-radio-group v-model="form.when" row>
            <v-radio :label="'Overnight'" value='later'></v-radio>
            <v-radio :label="'Now'" value='now'></v-radio>
          </v-radio-group>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="12">
          <v-checkbox v-model="form.skip_harvested" label="Skip Previously Harvested Reports" dense></v-checkbox>
        </v-col>
      </v-row>
      <div class="status-message" v-if="success || failure || working">
        <span v-if="success" class="good" role="alert" v-text="success"></span>
        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        <span v-if="working" class="work" role="alert" v-text="working"></span>
      </div>
      <v-row v-if="form.reports.length>0" no-gutters>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Submit</v-btn>
      </v-row>
      <v-row v-else-if="form.inst.length>0 && form.prov.length>0 && available_reports.length==0" no-gutters>
        <span class="form-fail" role="alert">No reports defined or available for selected Provider/Institution.</span>
      </v-row>
    </form>
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
            working: '',
            selections_made: false,
            form: new window.Form({
                inst: [],
                inst_group_id: 0,
                prov: [],
                reports: [],
                fromYM: '',
                toYM: '',
                when: 'later',
                skip_harvested: 1,
            }),
            fromMenu: false,
            toMenu: false,
            maxYM: '',
            inst_name: '',
            available_providers: [ ...this.providers],
            available_reports: [],
        }
    },
    methods: {
        resetForm () {
            // Reset form values
            this.form.inst = ( this.is_admin ) ? [] : [this.institutions[0].id];
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
                this.available_providers = [ ...this.providers];
            } else {
                this.updateProviders();
            }
            if (this.presets['prov_id']) this.verifyProvPreset();
            this.selections_made = true;
        },
        // Update mutable providers when inst changes
        onInstChange(inst_list) {
            if (inst_list.length == 0) {
                this.available_providers = [ ...this.providers];
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
                     this.available_providers = [ ...response.data.providers];
                 })
                 .catch(error => {});
        },
        onProvChange(prov_list) {
            this.failure = '';
            // If no providers, set to available to all
            if (prov_list.length == 0) {
                this.available_reports = [ ...this.all_reports];
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
                this.available_reports.sort((a,b) => {
                  if ( a.name < b.name ) return -1;
                  if ( a.name > b.name ) return 1;
                  return 0;
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
            if (this.form.toYM == '' || this.form.fromYM == '') {
                this.failure = 'Range of months to harvest is required';
                return;
            }
            if (this.form.toYM == '' && this.form.fromYM != '') this.form.toYM = this.form.fromYM;
            if (this.form.fromYM == '' && this.form.toYM != '') this.form.fromYM = this.form.toYM;
            this.working = ' ... Creating and updating harvest records ...';
            this.form.post('/harvests')
                .then((response) => {
                    if (response.result) {
                      this.working = '';
                        this.failure = '';
                        this.success = response.msg;
                        if (response.new_harvests.length>0) {
                          this.$emit('new-harvests', { harvests:response.new_harvests, bounds:response.bounds });
                        }
                        if (response.upd_harvests.length>0) {
                          this.$emit('updated-harvests', response.upd_harvests);
                        }
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
        },
    },
    computed: {
      ...mapGetters(['is_admin']),
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

</style>
