<template>
  <div>
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm">Reset Selections</v-btn>
    </div>
  	<form method="POST" action="" @submit.prevent="formSubmit"
  	      @keydown="form.errors.clear($event.target.name)">
      <div v-if="this.is_admin">
        <v-row class="d-flex align-mid ma-2" no-gutters>
          <v-col v-if="form.inst_group_id==0" class="d-flex px-2" cols="3" sm="3">
            <v-autocomplete :items="institution_options" v-model="form.inst" @change="onInstChange" multiple label="Institution(s)"
                            item-text="name" item-value="id" hint="Institution(s) to Harvest">
              <template v-if="is_admin || is_viewer" v-slot:prepend-item>
                <v-list-item @click="updateAllInsts">
                   <span v-if="allInsts">Clear Selections</span>
                   <span v-else>All Institutions</span>
                </v-list-item>
                <v-divider class="mt-1"></v-divider>
              </template>
              <template v-if="is_admin || is_viewer" v-slot:selection="{ item, index }">
                <span v-if="index==0 && institution_options.length==form.inst.length">
                  All Institutions
                </span>
                <span v-else-if="index==0 && !allInsts">{{ item.name }}</span>
                <span v-else-if="index===1 && !allInsts" class="text-grey text-caption align-self-center">
                  &nbsp; +{{ form.inst.length-1 }} more
                </span>
              </template>
            </v-autocomplete>
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
                          item-text="name" item-value="id" hint="Provider(s) to Harvest">
            <template v-slot:prepend-item>
              <v-list-item v-if="allConsoProvs || allProvs" @click="updateAllProvs('Clear')">
                 <span>Clear Selections</span>
              </v-list-item>
              <v-list-item v-if="!allConsoProvs && !allProvs" @click="updateAllProvs('ALL')">
                 <span>All Providers</span>
              </v-list-item>
              <v-list-item v-if="!allConsoProvs && !allProvs" @click="updateAllProvs('Conso')">
                 <span>All Consortium Providers</span>
              </v-list-item>
              <v-divider class="mt-1"></v-divider>
            </template>
            <template v-slot:selection="{ item, index }">
              <span v-if="index==0 && allProvs">All Providers</span>
              <span v-if="index==0 && allConsoProvs">All Consortium Providers</span>
              <span v-else-if="index<2 && !allConsoProvs && !allProvs">{{ item.name }}</span>
              <span v-else-if="index===2 && !allConsoProvs && !allProvs" class="text-grey text-caption align-self-center">
                &nbsp; +{{ form.prov.length-2 }} more
              </span>
              <span v-if="index <= 1 && index < form.prov.length-1 && !allConsoProvs && !allProvs">, </span>
            </template>
          </v-autocomplete>
        </v-col>
      </v-row>
      <v-row v-if="available_reports.length>0" class="d-flex ma-2" no-gutters>
        <v-col class="d-flex px-2" cols="6" sm="4">
          <v-select :items="available_reports" v-model="form.reports" item-text="legend" item-value="name" multiple chips
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
      <v-row v-if="form.reports.length>0 && (form.inst.length>0 || form.inst_group_id>0) && form.prov.length>0" no-gutters>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Submit</v-btn>
      </v-row>
      <v-row v-else-if="(form.inst.length>0 || form.inst_group_id>0) && form.prov.length>0 && available_reports.length==0" no-gutters>
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
            allProvs: false,
            allConsoProvs: false,
            allInsts: false,
            available_providers: [ ...this.providers],
            institution_options: [ ...this.institutions],
            available_reports: [],
            selected_insts: [],
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
            this.available_reports = [];
            this.allInsts = false;
            this.allProvs = false;
            this.allConsoProvs = false;
            if (this.form.inst.length == 0) {
                this.available_providers = [ ...this.providers];
            } else {
                this.updateProviders();
            }
            if (this.presets['prov_id']) this.verifyProvPreset();
        },
        // Verify provider preset value
        verifyProvPreset() {
            let preset_id = Number(this.presets['prov_id']);
            let prov = this.available_providers.find(p => p.id == preset_id);
            if (prov) {
                this.form.prov = [preset_id];
                this.onProvChange();
            } else {
                this.failure = 'The preset provider is not available - verify SUSHI credentials';
                this.form.prov = [];
                this.presets['prov_id'] = null;
            }
        },
        // Update mutable providers when inst-group changes
        onGroupChange(groupid) {
            if (groupid == 0) {
                this.available_providers = [ ...this.providers];
                this.selected_insts = [];
            } else {
                let group = this.inst_groups.find(g => g.id == groupid);
                if (typeof(group) != 'undefined') {
                    group.institutions.forEach(inst => { this.selected_insts.push(inst.id); });
                }
                this.updateProviders();
            }
            if (this.presets['prov_id']) this.verifyProvPreset();
            this.selections_made = true;
        },
        // Update mutable providers when inst changes
        onInstChange() {
          this.failure = '';
          this.form.inst_group_id = 0;
          this.allInsts = (this.form.inst.length == this.institutions.length) ? true : false;
          this.selected_insts = (this.allInsts) ? this.institutions.map(ii => ii.id) : [...this.form.inst];
          if (this.form.inst.length == 0) {
              this.available_providers = [ ...this.providers];
          } else {
              this.updateProviders();
          }
          if (this.presets['prov_id']) this.verifyProvPreset();
          this.selections_made = true;
        },
        // External axios call to return available providers
        updateProviders () {
            let inst_ids = (this.allInsts) ? JSON.stringify([0]) : JSON.stringify(this.form.inst);
            axios.get('/available-providers?inst_ids='+inst_ids+'&group_id='+this.form.inst_group_id)
                 .then((response) => {
                     this.available_providers = [ ...response.data.providers];
                 })
                 .catch(error => {});
        },
        onProvChange() {
            this.failure = '';
            // get the list of conso-provider IDs and set/update the All-Provider flags
            let conso_list = this.providers.filter(p => p.inst_id==1).map(p2 => p2.id);
            this.allProvs = (this.providers.length == this.form.prov.length && this.form.prov.length > 0);
            this.allConsoProvs = (!this.allProvs && conso_list.length == this.form.prov.length && this.form.prov.length > 0);
            // Setup prov_list with IDs
            let prov_list = [];
            if (this.allProvs) {
                prov_list = this.providers.map(p => p.id);
            } else if (this.allConsoProvs) {
                prov_list = [ ...conso_list];
            } else {
                prov_list = [ ...this.form.prov];
            }
            // If no providers, set reports to all
            if (prov_list.length == 0) {
                this.available_reports = [ ...this.all_reports];
                return;
            }
            // Update available reports when providers changes
            this.available_reports = [];
            prov_list.forEach(pid => {
                let cur_prov = this.providers.find(p => p.id == pid);
                if (typeof(cur_prov) == 'undefined') return;
                // cur_prov has no reports or we've already got all 4 turned on, skip the rest
                if (typeof(cur_prov.reports) == 'undefined') return;
                if (this.available_reports.length == 4) return;
                // loop across all report-type and check cur_prov to see if it should be enabled
                this.all_reports.forEach(rpt => {
                    // if already enabled or cur_prov missing the report in it's list, ship it
                    if (this.available_reports.some(r => r.name == rpt.name) ||
                        typeof(cur_prov.reports[rpt.name]) == 'undefined') return;
                    let add = false;
                    if (cur_prov.reports[rpt.name]=="ALL") {
                      add = true;
                    } else if (cur_prov.reports[rpt.name].length > 0) {
                      cur_prov.reports[rpt.name].forEach( inst => {
                        if (this.selected_insts.includes(inst)) add = true;
                      });
                    }
                    if (add) this.available_reports.push(rpt);
                });
            });
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
        // @change function for filtering/clearing all provider flags
        updateAllProvs(scope) {
          // Clear the flags and form value
          if (scope == 'Clear') {
              this.allProvs = false;
              this.allConsoProvs = false;
              this.form.prov = [];
              this.available_providers = [ ...this.providers];

          // Turn on all providers
          } else if (scope == 'ALL') {
              this.allProvs = true;
              this.allConsoProvs = false;
              this.form.prov = this.providers.map(p => p.id);
              this.available_providers = [ ...this.providers];
          // Turn on all Consortium Providers"
          } else {
              this.allProvs = false;
              this.allConsoProvs = true;
              this.form.prov = this.providers.filter(p => p.inst_id==1).map(p2 => p2.id);
              this.available_providers = this.providers.filter(p => this.form.prov.includes(p.id));
          }
          this.onProvChange();
        },
        // @change function for filtering/clearing all institutions
        updateAllInsts() {
          this.inst_group_id = 0;
          // All Insts is ON... turn it OFF and reset selected
          if (this.allInsts) {
            this.allInsts = false;
            this.form.inst = [];
            this.selected_insts = [];
          // All Insts is OFF... turn it ON and reset selected
          } else if (this.is_admin || this.is_viewer) {
            this.allInsts = true;
            this.form.inst = this.institutions.map(ii => ii.id);
            this.selected_insts = [... this.form.inst];
          }
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'is_viewer']),
    },
    mounted() {
      if ( !this.is_admin ) {
          this.form.inst = [this.institutions[0].id];
          this.inst_name = this.institutions[0].name;
          this.onInstChange();
      }
      let dt = new Date();
      this.maxYM = dt.getFullYear() + '-' + ('0' + (dt.getMonth()+1)).slice(-2);

      // Apply inbound institution preset (provider handled in the InstChange function)
      if (this.presets['inst_id']) {
          let instid = Number(this.presets['inst_id']);
          this.form.inst = [instid];
          this.onInstChange();
      }

      console.log('ManualHarvest Component mounted.');
    }
  }
</script>

<style>

</style>
