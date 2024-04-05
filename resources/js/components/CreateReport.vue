<template>
  <v-form ref="wizardForm">
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm('all')">Reset Selections</v-btn>
    </div>
    <v-row v-if="this.is_admin || this.is_viewer" class="d-flex mt-1 mx-2 align-mid" no-gutters>
      <v-col v-if="inst_group_id==0" class="d-flex px-2" cols="3" sm="3">
        <v-autocomplete :items="filter_options['inst']" v-model="selected_insts" label="Limit by Institution" multiple
                        @change="onInstChange" item-text="name" item-value="id" hint="Limit the report by institution"
        ></v-autocomplete>
      </v-col>
      <v-col v-if="selected_insts.length==0 && inst_group_id==0" class="d-flex pr-2 justify-center" cols="1" sm="1">
        <strong>OR</strong>
      </v-col>
      <v-col v-if="selected_insts.length==0" class="d-flex pa-0" cols="3" sm="3">
        <v-autocomplete :items="filter_options['group']" v-model="inst_group_id" label="Limit by Institution Group"
                        @change="onInstChange" item-text="name" item-value="id" hint="Limit the report to an institution group"
        ></v-autocomplete>
      </v-col>
    </v-row>
    <v-row class="d-flex mt-1 mx-2" no-gutters>
      <v-col class="d-flex" cols="3" sm="3">
        <v-container fluid>
          <v-autocomplete :items="filter_options['prov']" v-model="selected_provs" label="Limit by Provider" multiple
                          @change="onProvChange" item-text="name" item-value="id" hint="Limit the report by provider">
            <template #item="{ item, on, attrs }">
              <v-list-item v-on="on" v-bind="attrs" #default="{ active }">
                <v-list-item-action>
                  <v-checkbox :ripple="false" :input-value="active"></v-checkbox>
                </v-list-item-action>
                <v-list-item-avatar>
                  <v-icon>{{ providerIcon(item) }}</v-icon>
                </v-list-item-avatar>
                <v-list-item-content> {{ item.name }} </v-list-item-content>
              </v-list-item>
            </template>
          </v-autocomplete>
        </v-container>
      </v-col>
    </v-row>
    <h5>Choose a Report Type</h5>
    <v-row class="d-flex ma-0" no-gutters>
      The metrics and settings for the selected report or view can be customized in the next steps.
    </v-row>
    <v-row class="d-flex mt-1 mx-2" no-gutters>
      <v-col class="d-flex pa-0">
        <div v-if="working">
            <span>...Working... checking available data for requested Institution(s) and Provider(s)</span>
        </div>
        <div v-else-if="!haveData">
            <span><strong>There is no saved data for this combination of Institution(s) and Provider(s)</strong></span>
        </div>
        <div v-else>
        <v-radio-group v-model="selectedReport" :mandatory="false" @change="onReportChange">
          <v-expansion-panels multiple focusable>
            <v-expansion-panel v-if="report_data['TR'].count>0">
              <v-expansion-panel-header>
                <h4>Title</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                <p>Available Views</p>
                <v-radio :label="reports[0].legend+' ('+reports[0].name+')'" :value='reports[0]'></v-radio>
                <v-radio v-for="(value, idx) in tr_reports" :key="idx" :value="value"
                         :label="value.name+' : '+value.legend"></v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>

            <v-expansion-panel v-if="report_data['DR'].count>0">
              <v-expansion-panel-header>
                <h4>Database</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                  <p>Available Views</p>
                  <v-radio :label="reports[1].legend+' ('+reports[1].name+')'" :value='reports[1]'></v-radio>
                  <v-radio v-for="(value, idx) in dr_reports" :key="idx" :value="value"
                           :label="value.name+' : '+value.legend"></v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>

            <v-expansion-panel v-if="report_data['PR'].count>0">
              <v-expansion-panel-header>
                <h4>Platform</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                  <p>Available Views</p>
                  <v-radio :label="reports[2].legend+' ('+reports[2].name+')'" :value='reports[2]'></v-radio>
                  <v-radio v-for="(value, idx) in pr_reports" :key="idx" :value="value"
                           :label="value.name+' : '+value.legend"></v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>

            <v-expansion-panel v-if="report_data['IR'].count>0">
              <v-expansion-panel-header>
                <h4>Item</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                  <p>Available Views</p>
                  <v-radio :label="reports[3].legend+' ('+reports[3].name+')'" :value='reports[3]'></v-radio>
                  <v-radio v-for="(value, idx) in ir_reports" :key="idx" :value="value"
                           :label="value.name+' : '+value.legend"></v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-radio-group>
        </div>
      </v-col>
    </v-row>
    <h5 v-if="dialogs.date">Choose Report Dates</h5>
    <v-row v-if="dialogs.date" class="d-flex mt-1 mx-2" no-gutters>
        <v-col class="d-flex pa-0">
          <v-radio-group v-model="dateRange" @change="onDateRangeChange">
            <v-radio :label="'Latest Month ['+maxYM+']'" value='latestMonth'></v-radio>
            <v-radio :label="'Latest Year ['+latestYear+']'" value='latestYear'></v-radio>
            <v-radio :label="'Fiscal Year-to-Date ['+fiscalTD+']'" value='fiscalTD'></v-radio>
            <v-radio :label="'Custom Date Range'" value='Custom'></v-radio>
          </v-radio-group>
          <div v-if="dateRange=='Custom' || dateRange=='FYTD'" class="d-flex pa-2">
              <date-range :minym="minYM" :maxym="maxYM" :ymfrom="minYM" :ymto="maxYM"></date-range>
          </div>
        </v-col>
    </v-row>
    <v-row v-if="dialogs.done" class="d-flex mt-1 mx-2" no-gutters>
      <v-btn color="primary" small @click="goRedirect">Finish</v-btn>
    </v-row>
  </v-form>
</template>

<script>
//
// Future enhancement?
//  * Active.vs.Inactive in the lists... another option/flag?
//
  import { mapGetters } from 'vuex';
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            inst_groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            fields: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            fy_month: { type: Number, default: 1 },
    },
    data() {
        return {
            working: true,
            selections_made: false,
            dialogs: { date: false, done:false },
            selected_insts: [],
            selected_provs: [],
            inst_group_id: 0,
            selectedReport: {},
            masterId: 0,
            dateRange: '',
            minYM: '',
            maxYM: '',
            latestYear: '',
            fiscalTD: '',
            tr_reports: this.reports[0].children,
            dr_reports: this.reports[1].children,
            pr_reports: this.reports[2].children,
            ir_reports: this.reports[3].children,
            report_data: {},
            filter_options: {'inst': [], 'prov': [], 'group': []},
            limit_inst_ids: [],
            limit_prov_ids: [],
        }
    },
    watch: {
      //watcher to watch for changes to masterId
      masterId: {
        handler() {
            if (this.masterId == 0) return;  // reset?
            let key = this.reports[this.masterId-1].name;
            this.maxYM = this.report_data[key].YM_max;
            this.minYM = this.report_data[key].YM_min;
            // Setup latestYear string
            var max_parts = this.maxYM.split("-");
            var firstMonth = new Date(max_parts[0], max_parts[1] - 1, 1);
            firstMonth.setMonth(firstMonth.getMonth()-11);
            var ym_from = firstMonth.toISOString().substring(0,7);
            if (ym_from<this.minYM) {
                ym_from = this.minYM;
            }
            this.latestYear = ym_from+' to '+this.maxYM;
            // Setup Fiscal YTD string
            var fyStartYr = ( this.fy_month > max_parts[1] ) ? max_parts[0]-1 : max_parts[0];
            var fyFirstMonth = new Date(fyStartYr, this.fy_month-1, 1);
            var ym_from = fyFirstMonth.toISOString().substring(0,7);
            this.fiscalTD = ym_from+' to '+this.maxYM;
        },
      }
    },
    methods: {
        resetForm (type) {
            // Reset dialogs
            this.$refs.wizardForm.reset();
            this.dialogs.date = false;
            this.dialogs.done = false;
            this.selections_made = false;
            // Reset locally bound variables
            this.selected_insts = [];
            this.selected_provs = [];
            this.masterId = 0;
            this.inst_group_id = 0;
            this.selectedReport = {};
            // Reset the data store
            this.$store.dispatch('updateInstitutionFilter',[]);
            this.$store.dispatch('updateInstGroupFilter',0);
            this.$store.dispatch('updateProviderFilter',[]);
            this.$store.dispatch('updateReportId',1);
            if (type == 'all') this.updateAvailable('all');
        },
        onInstChange () {
            if (this.selected_insts.length > 0) {
                this.limit_inst_ids = [ ...this.selected_insts ];
                this.$store.dispatch('updateInstitutionFilter',this.selected_insts);
                this.updateAvailable('inst');
                this.selections_made = true;
            } else if (this.inst_group_id > 0) {
                this.limit_inst_ids = [];
                let group = this.inst_groups.find(g => g.id == this.inst_group_id);
                if (typeof(group) != 'undefined') {
                    group.institutions.forEach( (inst) => { this.limit_inst_ids.push(inst.id) } );
                }
                this.$store.dispatch('updateInstGroupFilter',this.inst_group_id);
                this.updateAvailable('group');
                this.selections_made = true;
            } else {
                this.inst_group_id = 0;
                this.limit_inst_ids = [];
            }
        },
        onProvChange () {
            this.limit_prov_ids = [ ...this.selected_provs ];
            this.$store.dispatch('updateProviderFilter',this.selected_provs);
            this.selections_made = true;
            this.updateAvailable('prov');
        },
        onReportChange () {
            // If selectedReport (or form) got reset
            if (typeof(this.selectedReport) == 'undefined') {    // got reset?
                return;
            }
            if (this.selectedReport == null || this.selectedReport == {}) return;
            // Setting master_id triggers the computed method above, which sets date-bounds
            let parent_id = this.reports[this.selectedReport.id-1].parent_id;
            if (parent_id == 0) {  // choice was a master report?
                this.masterId = this.selectedReport.id;
            } else {               // choice was a child report
                this.masterId = parent_id;
            }
            this.$store.dispatch('updateReportId',this.selectedReport.id);
            this.selections_made = true;
            this.dialogs.date = true;
        },
        onDateRangeChange () {
            if (this.dateRange != 'Custom') {    // date-range component updates store
                if (this.dateRange == 'latestMonth') {
                    var ym_f = this.maxYM;
                    var ym_t = this.maxYM;
                } else {    // latestYear
                    var _parts = this.latestYear.split(" ");
                    var ym_f = _parts[0];
                    var ym_t = this.maxYM;
                }
                this.$store.dispatch('updateFromYM',ym_f);
                this.$store.dispatch('updateToYM',ym_t);
            }
            this.selections_made = true;
            this.dialogs.done = true;
        },
        updateAvailable(filt) {
            let filters = JSON.stringify(this.all_filters);
            this.working = true;
            axios.get('/reports-available?filters='+filters)
                 .then((response) => {
                     this.report_data = response.data.reports;
                     this.working = false;
                 })
                 .catch(error => {});
            this.updateOptions(filt);
        },
        // Update inst, provider, and group filter options
        updateOptions(changed_filter) {
            // If no active filters, reset everything
            if (this.limit_inst_ids.length==0 && this.limit_prov_ids.length==0) {
                this.filter_options.inst = (this.is_admin || this.is_viewer) ? [...this.institutions] : [this.institutions[0]];
                this.filter_options.group = (this.is_admin || this.is_viewer) ? [...this.inst_groups] : [];
                this.filter_options.prov = (this.is_admin || this.is_viewer) ? this.providers.filter(p => p.inst_id == 1) :
                    this.providers.filter(p => (p.inst_id == 1 || p.inst_id == this.institutions[0].id));
                return;
            }
            // Set flag if changed_filter was just reset (so we can reset the options)
            let just_cleared = ( ( (changed_filter == 'inst' || changed_filter == 'group') && this.limit_inst_ids.length==0 ) ||
                                 ( changed_filter == 'prov' && this.limit_prov_ids.length==0 ) );

            // Rebuild options for Providers
            if (just_cleared || changed_filter != 'prov') {
              if (this.is_admin || this.is_viewer) {
                this.filter_options.prov = this.providers.filter(p => p.inst_id == 1 || this.limit_inst_ids.includes(p.inst_id));
              } else {
                this.filter_options.prov = this.providers.filter(p => (p.inst_id == 1 || p.inst_id == this.institutions[0].id));
              }
            }

            // Rebuild options for Insts and Groups (from Insts)
            if ( just_cleared || (changed_filter == 'prov') ) {
              if (this.is_admin || this.is_viewer) {
                  let provider_inst_ids = this.filter_options.prov.map(p => p.inst_id);
                  this.filter_options['inst'] = this.institutions.filter( ii => (provider_inst_ids.includes(ii.id) ||
                                                                                this.limit_inst_ids.includes(ii.id)));
                  var group_ids = [];
                  this.inst_groups.forEach( (gg) => {
                    gg.institutions.forEach( (ii) => {
                      if ( this.filter_options['inst'].includes(ii.id) && !group_ids.includes(gg.id)) group_ids.push(gg.id);
                    });
                  });
                  this.filter_options['group'] = this.inst_groups.filter(g => (group_ids.includes(g.id) ||
                                                                               this.inst_group_id == g.id));
              } else {
                  this.filter_options['inst'] = [this.institutions[0]];
                  this.filter_options.group = [];
              }
           }

        },
        goRedirect () {
            // only pass filters that apply to the selected report
            let report_filters = {
                                  'report_id': this.selectedReport.id,
                                  'fromYM': this.all_filters['fromYM'],
                                  'toYM': this.all_filters['toYM'],
                                  'prov_id': this.selected_provs
                                };
            // Institution Group is not a field, but it IS a filter.
            if (this.inst_group_id > 0) {   // groups gets precedence over individual insts
                report_filters['institutiongroup_id'] = this.inst_group_id;
            } else {
                report_filters['inst_id'] = this.selected_insts;
            }
            this.fields.forEach(field => {
                if (field.report_id == this.selectedReport.id && typeof(field.column) != null) {
                    if (typeof(this.all_filters[field.column] != 'undefined')) {
                        report_filters[field.column] = this.all_filters[field.column];
                    }
                }
            });
            let params = {...report_filters, dateRange: this.dateRange};
            this.resetForm('fields');
            window.location.assign("/reports/preview?filters=" + JSON.stringify(params));
            return false;
        },
        providerIcon (prov) {
            return (prov.inst_id == 1) ? 'mdi-account-multiple' : 'mdi-home-outline';
        },
    },
    computed: {
      ...mapGetters(['is_admin','is_viewer','all_filters']),
      haveData() {
          let count=0;
          for (var key in this.report_data) {
              count += this.report_data[key].count;
          }
          return count>0;
      },
    },
    beforeCreate() {
      // Load existing store data
	    this.$store.commit('initialiseStore');
  	},
    beforeMount() {
      // Set page name in the store
      this.$store.dispatch('updatePageName','preview');
    },
    mounted() {
      // set dialog starting points and boundaries
      this.limit_prov_ids = [];
      if (this.is_admin || this.is_viewer) {
        this.selected_insts = [];
        this.limit_inst_ids = [];
        this.filter_options.inst = [...this.institutions];
        this.filter_options.group = [...this.inst_groups];
        this.filter_options.prov = this.providers.filter(p => p.inst_id == 1);
      } else {
        this.selected_insts = [this.institutions[0].id];
        this.limit_inst_ids = [this.institutions[0].id];
        this.filter_options.inst = [this.institutions[0]];
        this.filter_options.group = [];
        this.filter_options.prov = this.providers.filter(p => (p.inst_id == 1 || p.inst_id == this.institutions[0].id));
      }

      // Subscribe to store updates and intialize filters and options
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
      this.$store.dispatch('updateInstitutionFilter',this.selected_insts);
      this.$store.dispatch('updateInstGroupFilter',this.inst_group_id);
      this.$store.dispatch('updateProviderFilter',this.selected_provs);
      this.updateAvailable('all');

      console.log('CreateReport Component mounted.');
    }
  }
</script>

<style>
.align-mid { align-items: center; }
</style>
