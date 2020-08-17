<template>
  <div class="ma-0 pa-0">
    <div class="d-flex flex-row mb-2">
      <div v-if="mutable_rangetype=='' || mutable_rangetype=='Custom'" class="d-flex pa-2">
        <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
        ></date-range>
      </div>
      <div v-else class="d-flex pa-2 align-center">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('dateRange')"/>&nbsp;
        <strong>Preset Date Range</strong>: {{ mutable_rangetype }}
      </div>
      <div class="d-flex pa-2">
        <v-switch v-model="zeroRecs" label="Exclude Zero-Use Records?"></v-switch>
      </div>
    </div>
    <div>
      <v-radio-group v-model="format" @change="changeFormat" row>
        <template v-slot:label>
          <strong>Report formatting</strong>
        </template>
        <v-radio label="CC+ Compact" value='Compact'></v-radio>
        <v-radio label="COUNTER-R5" value='COUNTER'></v-radio>
      </v-radio-group>
    </div>
    <v-expansion-panels multiple focusable :value="panels">
      <v-expansion-panel>
        <v-expansion-panel-header>Show/Hide Columns</v-expansion-panel-header>
        <v-expansion-panel-content>
          <v-row class="d-flex wrap-column-boxes ma-0" no-gutters>
            <v-col class="d-flex pa-2" cols="2" sm="2" v-for="field in mutable_fields" :key="field.id">
              <v-checkbox :label="field.text" v-model="field.active" :value="field.active"
                          @change="onFieldChange(field)"></v-checkbox>
            </v-col>
          </v-row>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <v-expansion-panel>
        <v-expansion-panel-header>Filters</v-expansion-panel-header>
        <v-expansion-panel-content>
          <v-row v-if="active_filter_count > 0" class="d-flex ma-1 wrap-filters" no-gutters>
            <div v-if='filter_data["provider"].active' cols="3" sm="2">
              <v-col v-if='filter_data["provider"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["provider"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('provider')"/>&nbsp;
                <v-select :items='filter_options.provider' v-model='filter_data.provider.value' multiple
                          @change="setFilter('provider')" label="Provider" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='filter_data["platform"].active' cols="3" sm="2">
              <v-col v-if='filter_data["platform"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["platform"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('platform')"/>&nbsp;
                <v-select :items='filter_options.platform' v-model='filter_data.platform.value' multiple
                          @change="setFilter('platform')" label="Platform" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='!filterGroup && filter_data["institution"].active'
                 cols="3" sm="2">
              <v-col v-if='filter_data["institution"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["institution"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('institution')"/>&nbsp;
                <v-select :items='filter_options.institution' v-model='filter_data.institution.value' multiple
                          @change="setFilter('institution')" label="Institution" item-text="name" item-value="id"
                ></v-select>
              </v-col>
            </div>
            <div v-if='!filterInst && filter_data["institutiongroup"].active' cols="3" sm="2">
              <v-col v-if='filter_data["institutiongroup"].value == 0' class="d-flex pa-2 align-center">
                <v-select :items='filter_options.institutiongroup' v-model='filter_data.institutiongroup.value'
                          @change="setFilter('institutiongroup')" label="Institution Group"
                          item-text="name" item-value="id"
                ></v-select>
              </v-col>
              <v-col v-if='filter_data["institutiongroup"].value > 0' class="d-flex pa-2 align-center" cols="3" sm="2">
                <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('institutiongroup')"/>&nbsp;
                Inst-Group: {{ filter_data["institutiongroup"].name }}
              </v-col>
            </div>
            <v-col v-if='filter_data["datatype"].value == 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <v-select :items='filter_options.datatype' v-model='filter_data.datatype.value' label="Data Type"
                        @change="setFilter('datatype')" item-text="name" item-value="id"
              ></v-select>
            </v-col>
            <v-col v-if='filter_data["datatype"].value > 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('datatype')"/>&nbsp;
              Datatype: {{ filter_data["datatype"].name }}
            </v-col>
            <v-col v-if='filter_data["sectiontype"].value > 0' class="d-flex pa-2 align-center">
              <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('sectiontype')"/>&nbsp;
              Section Type: {{ filter_data["sectiontype"].name }}
            </v-col>
            <v-col v-if='filter_data["sectiontype"].value == 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <v-select :items='filter_options.sectiontype' v-model='filter_data.sectiontype.value' label="SectionType"
                        @change="setFilter('sectiontype')" item-text="name" item-value="id"
              ></v-select>
            </v-col>
            <v-col v-if='filter_data["accesstype"].value > 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('accesstype')"/>&nbsp;
              Access Type: {{ filter_data["accesstype"].name }}
            </v-col>
            <v-col v-if='filter_data["accesstype"].value == 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <v-select :items='filter_options.accesstype' v-model='filter_data.accesstype.value' label="Access Type"
                        @change="setFilter('accesstype')" item-text="name" item-value="id"
              ></v-select>
            </v-col>
            <v-col v-if='filter_data["accessmethod"].value > 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('accessmethod')"/>&nbsp;
              Access Method: {{ filter_data["accessmethod"].name }}
            </v-col>
            <v-col v-if='filter_data["accessmethod"].value == 0' class="d-flex pa-2 align-center" cols="3" sm="2">
              <v-select :items='filter_options.accessmethod' v-model='filter_data.accessmethod.value'
                        label="Access Method" @change="setFilter('accessmethod')" item-text="name" item-value="id"
              ></v-select>
            </v-col>
            <div v-if='filter_data["yop"].active' cols="3" sm="2">
              <v-col v-if='filter_data["yop"].value.length >= 0' class="d-flex pa-2 align-center">
                <img v-if='filter_data["yop"].value.length > 0' src="/images/red-x-16.png"
                     alt="clear filter" @click="clearFilter('yop')"/>&nbsp;
                     <v-text-field label="YOP from" v-model="filter_data['yop'].value[0]" @change="setYOP()">
                     </v-text-field>&nbsp;
                     <v-text-field label="YOP to" v-model="filter_data['yop'].value[1]" @change="setYOP()">
                     </v-text-field>
              </v-col>
            </div>
          </v-row>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
    <v-row class="d-flex pt-4">
      <v-col class="d-flex pa-2" cols="4" sm="2">
        <v-btn class='btn' small type="button" color="primary" @click="previewData">{{ preview_text }}</v-btn>
      </v-col>
      <v-col class="d-flex pa-2" cols="4" sm="2">
        <v-btn class='btn' small type="button" color="primary" @click="showForm">Save Configuration</v-btn>
      </v-col>
      <v-col class="d-flex pa-2" cols="4" sm="2">
        <v-btn class='btn' small type="button" color="green" @click="goExport">Export</v-btn>
      </v-col>
    </v-row>
    <div v-if="!configForm">
      <v-row>
        <span class="form-good" role="alert" v-text="success"></span>
        <span class="form-fail" role="alert" v-text="failure"></span>
      </v-row>
    </div>
    <div v-else>
      <form method="POST" action="" @submit.prevent="saveConfig" @keydown="form.errors.clear($event.target.name)">
        <v-row class="d-flex pa-2" cols="17" sm="4">
          <v-col v-if="form.save_id==input_save_id" class="d-flex pa-2" cols="8" sm="4">
            <h5>Create a new saved configuration</h5>
          </v-col>
          <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex pa-2" cols="1" sm="1">&nbsp;</v-col>
          <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex pa-2" cols="8" sm="4">
            <h5>Overwrite an existing saved configuration</h5>
          </v-col>
        </v-row>
        <v-row class="d-flex">
          <v-col v-if="form.save_id==input_save_id" class="d-flex pa-2" cols="8" sm="4">
            <input name="save_id" id="save_id" value=0 type="hidden">
            <v-text-field v-model="form.title" label="Name" outlined></v-text-field>
          </v-col>
          <v-col v-if="form.title=='' && saved_reports.length>0 && form.save_id==input_save_id"
                 class="d-flex pa-2 justify-center" cols="1" sm="1">
            <h5>OR</h5>
          </v-col>
          <v-col v-if="form.title=='' && saved_reports.length>0" class="d-flex pa-2" cols="8" sm="4">
            <input id="title" name="title" value="" type="hidden">
            <v-select :items='saved_reports'
                      v-model='form.save_id'
                      label="Saved Report"
                      item-text="title"
                      item-value="id"
            ></v-select>
          </v-col>
        </v-row>
        <v-row class="d-flex">
          <v-col class="d-flex pa-2" cols="4" sm="2">
            <v-btn class='btn' small type="submit" color="green" :disabled="form.errors.any()">Save</v-btn>
          </v-col>
          <v-col class="d-flex pa-2" cols="4" sm="2">
            <v-btn class='btn' small type="button" @click="hideForm">Cancel</v-btn>
          </v-col>
        </v-row>
      </form>
    </div>
    <v-container v-if="showPreview" fluid>
      <v-data-table :headers="filteredHeaders" :items="report_data"
                    :loading="loading" :footer-props="footer_props" dense class="elevation-1">
        <template slot-scope="item">
          <tr>
            <template slot="headers" slot-scope="head">
              <td v-if="showColum(head.value)">{{ item[head.value] }}</td>
            </template>
          </tr>
        </template>
      </v-data-table>
    </v-container>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import Form from '@/js/plugins/Form';
  window.Form = Form;
  export default {
    props: {
        preset_filters: { type:Object, default: () => {} },
        columns: { type:Array, default: () => [] },
        fields: { type:Array, default: () => [] },
        saved_reports: { type:Array, default: () => [] },
        filter_options: { type:Object, default: () => {} },
        input_save_id: { type:Number, default: 0 },
        rangetype: { type:String, default: '' },
    },
    data () {
      return {
        showPreview: false,
        configForm: false,
        filterInst: false,
        filterGroup: false,
        preview_text: 'Display Preview',
        loading: true,
        panels: [1],
        minYM: '',
        maxYM: '',
        rangeKey: 1,
        active_filter_count: 0,
        zeroRecs: 1,
        footer_props: {
            'items-per-page-options': [10, 20, 50, 100],
        },
        report_data: [],
        filter_data: {
          provider: { col:'prov_id', act:'updateProvider', value:[], name:'', active: false },
          platform: { col:'plat_id', act:'updatePlatform', value:[], name:'', active: false },
          institution: { col:'inst_id', act:'updateInstitution', value:[], name:'', active: false },
          institutiongroup: { col:'institutiongroup_id', act:'updateInstGroup', value: -1, name:'', active: false },
          datatype: { col:'datatype_id', act:'updateDataType', value: -1, name:'', active: false },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value: -1, name:'', active: false },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value: -1, name:'', active: false },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value: -1, name:'', active: false },
          yop: { col:'yop', act:'updateYop', value:[], name:'', active: false },
        },
        mutable_fields: this.fields,
        mutable_cols: this.columns,
        mutable_rangetype: this.rangetype,
        cur_year: '',
        success: '',
        failure: '',
        runtype: '',
        format: 'Compact',
        form: new window.Form({
            title: '',
            save_id: this.input_save_id,
        })
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to update filter options
          this.updateColumns();
        }
      },
    },
    methods: {
        previewData (event) {
            this.runtype = 'preview';
            if (!this.showPreview) {
                this.showPreview = true;
                this.preview_text = 'Refresh Preview';
            }
            this.getReportData().then(data => {
                  this.report_data = data.items;
            });
        },
        onFieldChange(field) {
          if (typeof(this.filter_data[field.id]) != 'undefined') {    // column has a filter?
              var hasFilter=true;
              var action = this.filter_data[field.id].act+'Filter';
          } else {
              var hasFilter=false;
          }

          // If field is institution, reset flags regardless of enable.vs.disable
          if (field.id == 'institution') {
              this.filterInst = false;
              this.filterGroup = false;
          }

          // Turning on a field...
          if (field.active) {
              // If the field has filter, set it up
              if (hasFilter) {
                  // Turning on FIELD institution means enabling institution AND inst-group filters,
                  // but only for admins and managers...
                  if (field.id == 'institution') {
                      if (this.is_admin || this.is_viewer) {
                          this.filter_data.institution.active = true;
                          this.filter_data.institutiongroup.active = true;
                          this.filter_data.institution.value = [];
                          this.filter_data.institutiongroup.value = 0;
                          this.$store.dispatch(action,[]);
                          var act2 = this.filter_data.institutiongroup.act+'Filter';
                          this.$store.dispatch(act2,0);
                          this.active_filter_count += 2;
                      }
                  // Initialize filter values
                  } else {
                      this.filter_data[field.id].active = true;
                      if (this.filter_data[field.id].value.constructor === Array) {
                          this.filter_data[field.id].value = [];
                          this.$store.dispatch(action,[]);
                      } else {
                          this.filter_data[field.id].value = 0;
                          this.$store.dispatch(action,0);
                      }
                      this.active_filter_count++;
                  }

                  // Update the columns
                  this.updateColumns();
              }
              // Turn on the column(s)
              for (var col in this.mutable_cols) {
                  if (this.mutable_cols[col].field == field.id) this.mutable_cols[col].active = 1;
              }
          // Turning off a field...
          } else {
              // If the field has filter, clean it up
              if (hasFilter) {
                  this.filter_data[field.id].active = false;
                  if (this.filter_data[field.id].value.constructor === Array) {
                      this.filter_data[field.id].value = [];
                  } else {
                      this.filter_data[field.id].value = -1;
                  }

                  // Remove the filter from the list and suppress the column
                  this.$store.dispatch(action,this.filter_data[field.id].value);
                  this.updateColumns();
                  this.active_filter_count--;
                  if (field.id == 'institution') {
                      var act2 = this.filter_data.institutiongroup.act+'Filter';
                      this.filter_data.institutiongroup.value = -1;
                      this.$store.dispatch(act2,-1);
                      this.active_filter_count--;
                      this.filter_data.institutiongroup.active = false;
                  }
              }
              // Turn off the column(s)
              for (var col in this.mutable_cols) {
                  if (this.mutable_cols[col].field == field.id) this.mutable_cols[col].active = 0;
              }
          }
        },
        clearFilter(filter) {
            // Treat preset date range as a filter for UI
            // inbound: set to whatever was saved; cleared: show date-selectors instead
            if (filter == 'dateRange') {
                this.mutable_rangetype = '';
                return;
            }
            let method = this.filter_data[filter].act+'Filter';
            if (this.filter_data[filter].value.constructor === Array) {
                this.$store.dispatch(method, []);
                this.filter_data[filter].value = [];
            } else {
                this.$store.dispatch(method, 0);
                this.filter_data[filter].value = 0;
            }
            this.filter_data[filter].name = '';
            if (filter == 'institution' || filter == 'institutiongroup') {
                this.filterInst = false;
                this.filterGroup = false;
            }
        },
        setFilter(filter) {
            let method = this.filter_data[filter].act+'Filter';
            this.$store.dispatch(method, this.filter_data[filter].value);
            if (this.filter_data[filter].value.constructor != Array) {
                let idx = this.filter_options[filter].findIndex(f => f.id==this.filter_data[filter].value);
                this.filter_data[filter].name = this.filter_options[filter][idx].name;
            }
            if (filter == 'institution') this.filterInst = true;
            if (filter == 'institutiongroup') this.filterGroup = true;
        },
        setYOP() {
            this.failure = "";
            this.filter_data.yop.value.forEach((val, idx) => {
                if (!isNaN(val)) return;
                this.failure = "Only numbers allowed for YOP From-To values.";
                this.filter_data.yop.value[idx] = '';
            });
            if (this.filter_data.yop.value[0] == '') this.filter_data.yop.value[1] == '';
            if (this.filter_data.yop.value[0] == '' && this.filter_data.yop.value[1] == '') {
                this.filter_data['yop'].value = [0];
                this.$store.dispatch('updateYopFilter', [0]);
                return;
            }
            // Set Empty To to cur_year
            if (this.filter_data.yop.value[1] == '') this.filter_data.yop.value[1] = this.cur_year;
            // Empty From gets To
            if (this.filter_data.yop.value[0] == '') this.filter_data.yop.value[0] = this.filter_data.yop.value[1];
            // From>To throws error, To resets to current year
            if (this.filter_data.yop.value[0] > this.filter_data.yop.value[1]) {
                this.failure = "YOP:To automatically reset to "+this.cur_year;
                this.filter_data.yop.value[1] = this.cur_year;
            }
            this.$store.dispatch('updateYopFilter', this.filter_data.yop.value);
        },
        getReportData () {
          if (this.runtype != 'export') {
              this.loading = true;
          }

          //copy current params to modify
          let params = this.params;
          params['filters'] = JSON.stringify(this.all_filters);
          let _flds = {};
          this.mutable_fields.forEach(fld => {
            var fval = (typeof(this.filter_data[fld.id])=='undefined') ? '' : this.filter_data[fld.id].value;
            _flds[fld.id] = {active: fld.active, limit: fval};
          })
          params['fields'] = JSON.stringify(_flds);
          params['zeros'] = this.zeroRecs;
          params['format'] = this.format;

          if (this.runtype != 'export') {   // currently only other value is 'preview'
              return new Promise((resolve, reject) => {
                axios.get("/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&'))
                                .then((response) => {
                    let items = response.data.usage;
                    resolve({items});
                    this.loading = false;
                    this.runtype = '';
                })
                .catch(err => console.log(err));
              });
          } else {
              let a = document.createElement('a');
              a.target = 'blank';
              a.href = "/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&');
              a.click();
          }
        },
        changeFormat () {
          this.updateColumns();
        },
        updateColumns () {
          var self = this;
          axios.post('/update-report-columns', {
              filters: this.all_filters,
              fields: this.mutable_fields,
              format: this.format
          })
          .then( function(response) {
              if (response.data.result) {
                  self.mutable_cols = response.data.columns;
                  self.rangeKey += 1;           // force re-render of the date-range component
              } else {
                  self.failure = response.data.msg;
              }
          })
          .catch(error => {});
        },
        showForm (event) {
            this.configForm = true;
        },
        hideForm (event) {
            this.form.title = '';
            this.form.save_id = this.input_save_id;
            this.configForm = false;
        },
        saveConfig() {
            if (this.form.title=='' && this.form.save_id==0) {
                this.failure = 'A name is required to save the configuration';
                return;
            }
            let _flds = {};
            this.mutable_fields.forEach(fld => {
              var fval = (typeof(this.filter_data[fld.id])=='undefined') ? '' : this.filter_data[fld.id].value;
              _flds[fld.id] = {active: fld.active, limit: fval};
            })
            if (!this.filterInst) {   // If filtering by-inst-group, add to the cols array
                _flds['institutiongroup'] = {active: false, limit: this.filter_data.institutiongroup.value};
            }
            let num_months = 1;     // default to lastMonth
            if (this.preset_filters.dateRange == 'latestYear') {
                num_months = 12;
            } else if (this.preset_filters.dateRange == 'Custom') {
                var from_parts = this.filter_by_fromYM.split("-");
                var to_parts = this.filter_by_toYM.split("-");
                var fromDate = new Date(from_parts[0], from_parts[1]-1, 1);
                var toDate = new Date(to_parts[0], to_parts[1]-1, 1);
                num_months = toDate.getMonth() - fromDate.getMonth() +
                         (12 * (toDate.getFullYear() - fromDate.getFullYear())) + 1;
            }
            axios.post('/savedreports', {
                title: this.form.title,
                save_id: this.form.save_id,
                report_id: this.all_filters.report_id,
                date_range: this.preset_filters.dateRange,
                from: this.filter_by_fromYM,
                to: this.filter_by_toYM,
                fields: JSON.stringify(_flds),
            })
            .then((response) => {
                if (response.data.result) {
                    this.success = response.data.msg;
                } else {
                    this.failure = response.data.msg;
                }
                this.configForm = false;
            })
            .catch(error => {});
        },
        goExport() {
            this.runtype = 'export';
            this.getReportData().then(data => {
                this.runtype = '';
            });
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'is_viewer', 'all_filters', 'all_options', 'filter_by_fromYM', 'filter_by_toYM']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
      params(nv) {  // Computed params to return pagination and settings
        return {
            preview: 100,
            runtype: this.runtype,
            report_id: this.all_filters.report_id,
        };
      },
      filteredHeaders() {
        return this.mutable_cols.filter(h => h.active)
      },
    },
    mounted() {
      // Set initial filter-state for inactive "filterable" columns, and count the active ones
      this.mutable_cols.forEach(col => {
        let idx = col.value;
        if (typeof(this.filter_data[idx]) != 'undefined') {    // filtered column?
            var action = this.filter_data[idx].act+'Filter';
            if (col.active) {
                if (this.filter_data[idx].value.constructor === Array) {
                    this.filter_data[idx].value = [];
                } else {
                    this.filter_data[idx].value = 0;
                }
                this.$store.dispatch(action,this.filter_data[idx].value);
                this.filter_data[idx].active = true;
                this.active_filter_count++;
            } else {
                if (this.filter_data[idx].value.constructor === Array) {
                    this.filter_data[idx].value = [];
                } else {
                    this.filter_data[idx].value = -1;
                }
                this.$store.dispatch(action,-1);
            }
        }
      });
      // Assign preset filter values
      for (let [key, data] of Object.entries(this.filter_data)) {
          if (typeof(this.preset_filters[data.col]) != 'undefined') {
              let filt = data.act+'Filter';
              this.$store.dispatch(filt,this.preset_filters[data.col]);
              if (this.preset_filters[data.col].constructor === Array) {
                  data.value = this.preset_filters[data.col].slice();
              } else {
                  if (this.preset_filters[data.col] > 0) {
                    data.value = this.preset_filters[data.col];
                    let idx = this.filter_options[key].findIndex(f => f.id==data.value);
                    data.name = this.filter_options[key][idx].name;
                  }
              }
          }
      }

      if (this.is_admin || this.is_viewer) {
          if (this.preset_filters['institutiongroup_id']>0) {
              this.filterGroup = true;
          }
          // filter by inst if preset defined - BUT only if group filtering is inactive (group > inst)
          if (this.preset_filters['inst_id'].length>0 && !this.filterGroup) {
              this.filterInst = true;
          }
          // Since group is not a column (was skipped above), bump the counter if the filter is on
          if (!this.filterInst && this.filter_data.institutiongroup.value == 0) {
              this.active_filter_count++;
          }
      }

      // Assign preset report_id, and from/to date fields to the store variables
      this.$store.dispatch('updateReportId',this.preset_filters['report_id']);
      this.$store.dispatch('updateFromYM',this.preset_filters['fromYM']);
      this.$store.dispatch('updateToYM',this.preset_filters['toYM']);
      if (this.mutable_rangetype == 'latestYear') this.mutable_rangetype = "Up to latest 12 months";
      if (this.mutable_rangetype == 'latestMonth') this.mutable_rangetype = "Most recent available month";

      // Set options for all filters and in the datastore
      this.rangeKey += 1;           // force re-render of the date-range component

      // Get current year
      this.cur_year = (new Date()).getFullYear();
      console.log('ReportPreview Component mounted.');
    }
  }
</script>
<style>
.wrap-column-boxes {
    flex-flow: row wrap;
    align-items: flex-end;
 }
 .wrap-filters {
     flex-flow: row wrap;
     align-items: center;
  }
.form-fail {
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
