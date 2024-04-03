<template>
  <v-form ref="wizardForm">
    <div v-if="selections_made">
      <v-btn color="gray" small @click="resetForm('all')">Reset Selections</v-btn>
    </div>
    <div v-if="this.is_admin || this.is_viewer">
      <v-row class="d-flex align-mid" no-gutters>
        <v-col v-if="inst_group_id==0" class="d-flex ma-2" cols="3" sm="3">
          <v-autocomplete :items="institutions" v-model="inst" label="Limit by Institution" @change="onInstChange" multiple
                          item-text="name" item-value="id" hint="Limit the report by institution"
          ></v-autocomplete>
        </v-col>
        <v-col v-if="inst==0 && inst_group_id==0 " class="d-flex" cols="1" sm="1"><strong>OR</strong></v-col>
        <v-col v-if="inst==0" class="d-flex ma-2" cols="3" sm="3">
          <v-autocomplete :items="inst_groups" v-model="inst_group_id" label="Limit by Institution Group" @change="onGroupChange"
                          item-text="name" item-value="id" hint="Limit the report to an institution group"
          ></v-autocomplete>
        </v-col>
      </v-row>
    </div>
    <v-row class="mb-0 py-0" no-gutters>
      <v-col class="ma-2" cols="3" sm="3">
        <v-container fluid>
          <v-autocomplete :items="providers" v-model="prov" label="Limit by Provider" @change="onProvChange" multiple
                          item-text="name" item-value="id" hint="Limit the report by provider">
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
    <v-row class="mb-0 py-0" no-gutters>
      <v-col class="ma-2 pa-0">
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
                <p>
                    Available Views<br />
                    Your selection here will provide you with some default settings to get started, but you'll
                    still be able to customize the report if you need to.
                </p>
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
    <v-row v-if="dialogs.date" class="d-flex ma-0" no-gutters>
        <v-col class="ma-2 pa-0">
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
    <v-row v-if="dialogs.done" no-gutters>
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
            inst: [],
            prov: [],
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
            this.inst = 0;
            this.prov = 0;
            this.masterId = 0;
            this.inst_group_id = 0;
            this.selectedReport = {};
            // Reset the data store
            this.$store.dispatch('updateInstitutionFilter',[]);
            this.$store.dispatch('updateInstGroupFilter',0);
            this.$store.dispatch('updateProviderFilter',[]);
            this.$store.dispatch('updateReportId',1);
            if (type == 'all') this.updateAvailable();
        },
        onInstChange () {
            this.$store.dispatch('updateInstitutionFilter',this.inst);
            this.selections_made = true;
            this.updateAvailable();
        },
        onGroupChange () {
            this.$store.dispatch('updateInstGroupFilter',this.inst_group_id);
            this.selections_made = true;
            this.updateAvailable();
        },
        onProvChange () {
            this.$store.dispatch('updateProviderFilter',this.prov);
            this.selections_made = true;
            this.updateAvailable();
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
        updateAvailable () {
            let filters = JSON.stringify(this.all_filters);
            this.working = true;
            axios.get('/reports-available?filters='+filters)
                 .then((response) => {
                     this.report_data = response.data.reports;
                     this.working = false;
                 })
                 .catch(error => {});
        },
        goRedirect () {
            // only pass filters that apply to the selected report
            let report_filters = {
                                  'report_id': this.selectedReport.id,
                                  'fromYM': this.all_filters['fromYM'],
                                  'toYM': this.all_filters['toYM'],
                                  'prov_id': this.prov
                                };
            // Institution Group is not a field, but it IS a filter.
            if (this.inst_group_id > 0) {   // groups gets precedence over individual insts
                report_filters['institutiongroup_id'] = this.inst_group_id;
            } else {
                report_filters['inst_id'] = this.inst;
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
      // set dialog starting point
      if (!this.is_admin && !this.is_viewer) {
          this.inst=[this.institutions[0].id];
      }

      // Subscribe to store updates and intialize filters and options
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
      this.$store.dispatch('updateInstitutionFilter',this.inst);
      this.$store.dispatch('updateInstGroupFilter',this.inst_group_id);
      this.$store.dispatch('updateProviderFilter',this.prov);
      this.updateAvailable();

      console.log('CreateReport Component mounted.');
    }
  }
</script>

<style>
.align-mid { align-items: center; }
</style>
