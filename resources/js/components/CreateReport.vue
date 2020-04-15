<template>
  <v-form ref="wizardForm">
    <v-row v-if="dialogs.inst">
      <v-col class="ma-2" cols="2" sm="2">
        <h4>Choose Institution(s)</h4>
      </v-col>
      <v-col v-if="inst_id!==null || inst_group_id!==0">
        <v-btn color="gray" small @click="resetForm">Reset Selections</v-btn>
      </v-col>
    </v-row>
    <v-row v-if="dialogs.inst && inst_group_id==0">
      <v-col class="ma-2" cols="3" sm="3">
        <v-select
            :items="mutable_insts"
            v-model="inst_id"
            @change="onInstChange"
            label="Institution"
            item-text="name"
            item-value="id"
            hint="Limit the report by institution"
        ></v-select>
      </v-col>
    </v-row>
    <v-row v-if="dialogs.inst && inst_id==null">
      <v-col class="ma-2" cols="3" sm="3">
        <v-select
            :items="mutable_groups"
            v-model="inst_group_id"
            @change="onGroupChange"
            label="Institution Group"
            item-text="name"
            item-value="id"
            hint="Limit the report to an institution group"
        ></v-select>
      </v-col>
    </v-row>

    <v-row v-if="dialogs.prov">
      <v-col class="ma-2" cols="3" sm="3">
        <span><h4>Choose Provider(s)</h4></span>
        <v-select
            :items="providers"
            v-model="prov_id"
            @change="onProvChange"
            label="Provider"
            item-text="name"
            item-value="id"
            hint="Limit the report by provider"
        ></v-select>
      </v-col>
    </v-row>
    <v-row v-if="dialogs.rept">
      <span><h4>Choose a Report Type</h4></span>
      <v-col class="ma-2" cols="12">
        <div v-if="!haveData">
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
                <v-radio :label="reports[0].legend+' ('+reports[0].name+')'" value='1'></v-radio>
                <v-radio v-for="(value, idx) in tr_reports" :key="idx" :value="value"
                         :label="value.name+' : '+value.legend">Hello World</v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>

            <v-expansion-panel v-if="report_data['DR'].count>0">
              <v-expansion-panel-header>
                <h4>Database</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                  <p>Available Views</p>
                  <v-radio :label="reports[1].legend+' ('+reports[0].name+')'" value='2'></v-radio>
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
                  <v-radio :label="reports[2].legend+' ('+reports[0].name+')'" value='3'></v-radio>
                  <v-radio v-for="(value, idx) in pr_reports" :key="idx" :value="value"
                           :label="value.name+' : '+value.legend">Hi Sailor!</v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>

            <v-expansion-panel v-if="report_data['IR'].count>0">
              <v-expansion-panel-header>
                <h4>Item</h4>
              </v-expansion-panel-header>
              <v-expansion-panel-content>
                  <p>Available Views</p>
                  <v-radio :label="reports[2].legend+' ('+reports[0].name+')'" value='4'></v-radio>
                  <v-radio v-for="(value, idx) in ir_reports" :key="idx" :value="value"
                           :label="value.name+' : '+value.legend"></v-radio>
              </v-expansion-panel-content>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-radio-group>
        </div>
      </v-col>
    </v-row>

    <v-row v-if="dialogs.date">
        <span><h4>Choose Report Dates</h4></span>
        <v-col class="ma-2" cols="12">
          <v-radio-group v-model="dateRange" @change="dialogs.done=true">
            <v-radio :label="'Latest Month ['+latestMonth+']'" :value='latestMonth'></v-radio>
            <v-radio :label="'Latest Year ['+latestYear+']'" :value='latestYear'></v-radio>
            <v-radio :label="'Custom Date Range'"></v-radio>
          </v-radio-group>
        </v-col>
    </v-row>

    <v-row v-if="dialogs.done">
      <v-btn color="green" small @click="">Finish</v-btn>
    </v-row>
  </v-form>
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            inst_groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            fields: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            //?? this ??
            // date_choices: { type:Array, default: () => [] },
    },

    data() {
        return {
            dialogs: { inst: false, prov: false, rept: false, date: false, done:false },
            inst_id: null,
            prov_id: null,
            inst_group_id: 0,
            selectedReport: {},
            masterId: 0,
            dateRange: '',
            latestYear: '',
            latestMonth: '',
            mutable_insts: this.institutions,
            mutable_groups: this.inst_groups,
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
            let key = this.reports[this.masterId-1].name;
            this.latestMonth = this.report_data[key].YM_max;
            var ym_to = this.latestMonth;
            var from_parts = this.report_data[key].YM_max.split("-");
            var fromDate = new Date(from_parts[0], from_parts[1] - 1, 1);
            fromDate.setMonth(fromDate.getMonth()-11);
            var ym_from = fromDate.toISOString().substring(0,7);
            if (ym_from<this.report_data[key].YM_min) {
                ym_from = this.report_data[key].YM_min;
            }
            this.latestYear = ym_from+' to '+ym_to;
        },
      }
    },
    methods: {
        resetForm () {
            // Reset dialogs
            this.$refs.wizardForm.reset();
            if (this.is_admin || this.is_viewer) {
                this.dialogs.inst = true;
            }
            this.dialogs.prov = false;
            this.dialogs.rept = false;
            this.dialogs.date = false;
            // Reset locally bound variables
            this.inst_id = null,
            this.prov_id = null,
            this.inst_group_id = 0,
            this.mutable_insts = this.institutions;
            this.mutable_groups = this.inst_groups;
            // Reset the data store
            this.$store.dispatch('updateInstitutionFilter',0);
            this.$store.dispatch('updateInstGroupFilter',0);
            this.$store.dispatch('updateProviderFilter',0);
            this.$store.dispatch('updateMasterId',1);
        },
        onInstChange () {
            this.$store.dispatch('updateInstitutionFilter',this.inst_id);
            this.dialogs.prov = true;
            this.updateAvailable();
        },
        onGroupChange () {
            this.$store.dispatch('updateInstGroupFilter',this.inst_group_id);
            this.dialogs.prov = true;
            this.updateAvailable();
        },
        onProvChange () {
            this.$store.dispatch('updateProviderFilter',this.prov_id);
            this.dialogs.rept = true;
            this.updateAvailable();
        },
        onReportChange () {
            let parent_id = this.reports[this.selectedReport.id-1].parent_id;
            if (parent_id == 0) {  // choice was a master report?
                this.masterId = this.selectedReport.id-1
            } else {               // choice was a child report
                this.masterId = parent_id;
            }
            this.$store.dispatch('updateMasterId',this.masterId);
            this.dialogs.date = true;
        },
        updateAvailable () {
            let filters = JSON.stringify(this.all_filters);
            axios.get('/reports-available?filters='+filters)
                 .then((response) => {
                     this.report_data = response.data.reports;
                 })
                 .catch(error => {});
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
    mounted() {
      if (this.is_admin || this.is_viewer) {
          this.dialogs['inst'] = true;
      } else {
          this.dialogs['prov'] = true;
      }
      this.user_inst=this.institutions[0];
      console.log('CreateReport Component mounted.');
    }
  }
</script>

<style>
</style>
