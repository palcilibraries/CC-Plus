<template>
  <div>
    <date-range :minym="minYM" :maxym="maxYM"
                :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM"
                :key="rangeKey"
    ></date-range>
    <span><strong>Show/Hide Columns</strong></span>
    <v-row class="mb-0 py-0">
      <v-col class="d-flex align-bot" v-for="field in mutable_fields" :key="field.id">
        <v-checkbox :label="field.text" v-model="field.active" :value="field.active"
                    @change="onFieldChange(field)"></v-checkbox>
      </v-col>
    </v-row>
    <span v-if="active_filter_count > 0"><strong>Filters</strong></span>
    <v-row v-if="active_filter_count > 0" no-gutters>
      <div v-if='filter_data["provider"].value.constructor === Array' class="d-flex pr-4 align-mid" cols="3" sm="2">
        <v-col v-if='filter_data["provider"].value.length >= 0' class="d-flex align-mid">
          <img v-if='filter_data["provider"].value.length > 0' src="/images/red-x-16.png"
               alt="clear filter" @click="clearFilter('provider')"/>&nbsp;
          <v-select :items='filter_options.provider'
                    v-model='filter_data.provider.value'
                    multiple
                    @change="setFilter('provider')"
                    label="Provider"
                    item-text="name"
                    item-value="id"
          ></v-select>
        </v-col>
      </div>
      <div v-if='filter_data["platform"].value.constructor === Array' class="d-flex pr-4 align-mid" cols="3" sm="2">
        <v-col v-if='filter_data["platform"].value.length >= 0' class="d-flex align-mid">
          <img v-if='filter_data["platform"].value.length > 0' src="/images/red-x-16.png"
               alt="clear filter" @click="clearFilter('platform')"/>&nbsp;
          <v-select :items='filter_options.platform'
                    v-model='filter_data.platform.value'
                    multiple
                    @change="setFilter('platform')"
                    label="Platform"
                    item-text="name"
                    item-value="id"
          ></v-select>
        </v-col>
      </div>
      <div v-if='(is_admin || is_viewer) && !filterGroup && filter_data["institution"].value.constructor === Array'
           class="d-flex pr-4 align-mid" cols="3" sm="2">
        <v-col v-if='filter_data["institution"].value.length >= 0' class="d-flex align-mid">
          <img v-if='filter_data["institution"].value.length > 0' src="/images/red-x-16.png"
               alt="clear filter" @click="clearFilter('institution')"/>&nbsp;
          <v-select :items='filter_options.institution'
                    v-model='filter_data.institution.value'
                    multiple
                    @change="setFilter('institution')"
                    label="Institution"
                    item-text="name"
                    item-value="id"
          ></v-select>
        </v-col>
      </div>
      <div v-if='(is_admin || is_viewer) && !filterInst' class="d-flex pr-4 align-mid">
        <v-col v-if='filter_data["institutiongroup"].value == 0' class="d-flex align-mid">
          <v-select :items='filter_options.institutiongroup'
                    v-model='filter_data.institutiongroup.value'
                    @change="setFilter('institutiongroup')"
                    label="Institution Group"
                    item-text="name"
                    item-value="id"
          ></v-select>
        </v-col>
        <v-col v-if='filter_data["institutiongroup"].value > 0' class="d-flex align-mid">
          <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('institutiongroup')"/>&nbsp;
          Inst-Group: {{ filter_data["institutiongroup"].name }}
        </v-col>
      </div>
      <v-col v-if='filter_data["datatype"].value == 0' class="d-flex pr-4 align-mid" cols="3" sm="2">
        <v-select :items='filter_options.datatype'
                  v-model='filter_data.datatype.value'
                  @change="setFilter('datatype')"
                  label="Data Type"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["datatype"].value > 0' class="d-flex pr-4 align-mid" cols="3" sm="2">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('datatype')"/>&nbsp;
        Datatype: {{ filter_data["datatype"].name }}
      </v-col>
      <v-col v-if='filter_data["sectiontype"].value > 0' class="d-flex pr-4 align-mid">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('sectiontype')"/>&nbsp;
        Section Type: {{ filter_data["sectiontype"].name }}
      </v-col>
      <v-col v-if='filter_data["sectiontype"].value == 0' class="d-flex pr-4 align-mid" cols="3" sm="3">
        <v-select :items='filter_options.sectiontype'
                  v-model='filter_data.sectiontype.value'
                  @change="setFilter('sectiontype')"
                  label="SectionType"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["accesstype"].value > 0' class="d-flex pr-4 align-mid" cols="3" sm="3">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('accesstype')"/>&nbsp;
        Access Type: {{ filter_data["accesstype"].name }}
      </v-col>
      <v-col v-if='filter_data["accesstype"].value == 0' class="d-flex pr-4 align-mid" cols="3" sm="3">
        <v-select :items='filter_options.accesstype'
                  v-model='filter_data.accesstype.value'
                  @change="setFilter('accesstype')"
                  label="Access Type"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["accessmethod"].value > 0' class="d-flex align-mid" cols="3" sm="3">
        <img src="/images/red-x-16.png" alt="clear filter" @click="clearFilter('accessmethod')"/>&nbsp;
        Access Method: {{ filter_data["accessmethod"].name }}
      </v-col>
      <v-col v-if='filter_data["accessmethod"].value == 0' class="d-flex align-mid" cols="3" sm="3">
        <v-select :items='filter_options.accessmethod'
                  v-model='filter_data.accessmethod.value'
                  @change="setFilter('accessmethod')"
                  label="Access Method"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
    </v-row>
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
    },
    data () {
      return {
        showPreview: false,
        configForm: false,
        filterInst: false,
        filterGroup: false,
        preview_text: 'Display Preview',
        change_counter: 0,
        totalRecs: 0,
        filter_drawer: null,
        column_drawer: null,
        loading: true,
        minYM: '',
        maxYM: '',
        rangeKey: 1,
        active_filter_count: 0,
        footer_props: {
            'items-per-page-options': [10, 20, 50, 100],
        },
        report_data: [],
        filter_data: {
          provider: { col:'prov_id', act:'updateProvider', value:[], name:'' },
          platform: { col:'plat_id', act:'updatePlatform', value:[], name:'' },
          institution: { col:'inst_id', act:'updateInstitution', value:[], name:'' },
          institutiongroup: { col:'institutiongroup_id', act:'updateInstGroup', value:0, name:'' },
          datatype: { col:'datatype_id', act:'updateDataType', value: -1, name:'' },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value: -1, name:'' },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value: -1, name:'' },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value: -1, name:'' },
        },
        mutable_fields: this.fields,
        mutable_cols: this.columns,
        success: '',
        failure: '',
        runtype: '',
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
            this.getData().then(data => {
                  this.report_data = data.items;
            });
        },
        showColumn(col) {
            return this.mutable_cols.find(h => h.value === col).active
        },
        onFieldChange(field) {
          if (typeof(this.filter_data[field.id]) != 'undefined') {    // column has a filter?
              var hasFilter=true;
              var theFilter = this.filter_data[field.id];
              var action = theFilter.act+'Filter';
          } else {
              var hasFilter=false;
          }

          // If field is institution, we need to reset flags regardless of enable.vs.disable
          if (field.id == 'institution') {
              this.filterInst = false;
              this.filterGroup = false;
          }

          // Turning on a field...
          if (field.active) {
              if (hasFilter) {
                  // Turning on FIELD institution means enabling institution AND inst-group filters
                  if (field.id == 'institution') {
                      this.filter_data['institution'].value = [];
                      this.$store.dispatch(action,[]);
                      var act2 = this.filter_data['institutiongroup'].act+'Filter';
                      this.filter_data['institutiongroup'].value = 0;
                      this.$store.dispatch(act2,0);
                      this.active_filter_count += 2;

                  // Set filter to "all"
                  } else {
                      if (field.id == 'provider' || field.id == 'platform') {
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
              if (hasFilter) {
                  // Remove the filter from the list and suppress the column
                  this.filter_data[field.id].value = -1;
                  this.$store.dispatch(action,-1);
                  this.updateColumns();
                  this.active_filter_count--;
                  if (field.id == 'institution') {
                      var act2 = this.filter_data['institutiongroup'].act+'Filter';
                      this.filter_data['institutiongroup'].value = -1;
                      this.$store.dispatch(act2,-1);
                      this.active_filter_count--;
                  }
              }
              // Turn off the column(s)
              for (var col in this.mutable_cols) {
                  if (this.mutable_cols[col].field == field.id) this.mutable_cols[col].active = 0;
              }
          }
        },
        clearFilter(filter) {
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
        getData () {
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

          if (this.runtype != 'export') {   // currently only other value is 'preview'
              return new Promise((resolve, reject) => {
                axios.get("/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&'))
                                .then((response) => {
                    let items = response.data.usage;
                    this.totalRecs = response.data.usage.length;
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
        updateColumns () {
          var self = this;
          axios.post('/update-report-columns', {
              filters: this.all_filters,
              fields: this.mutable_fields
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
                _flds['institutiongroup'] = {active: false, limit: this.filter_data['institutiongroup'].value};
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
            this.getData().then(data => {
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
      this.mutable_cols.forEach(head => {
        if (typeof(this.filter_data[head.value]) != 'undefined') {    // filtered column?
            var theFilter = this.filter_data[head.value];
            var action = theFilter.act+'Filter';
            if (head.active) {
                theFilter.value = 0;
                this.$store.dispatch(action,0);
                this.active_filter_count++;
            } else {
                theFilter.value = -1;
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

      // Inst-Group is not a column - if the filter is active, bump the counter
      if (this.preset_filters['institutiongroup_id']>0) this.filterGroup = true;
      if (this.preset_filters['inst_id']>0 && !this.filterGroup) this.filterInst = true;
      if ((this.is_admin || this.is_viewer) && !this.filterInst && this.filter_data["institutiongroup"].value == 0) {
          this.active_filter_count++;
      }

      // Manually disable platform filtering for platform reports
      if (this.preset_filters['report_id']==3 || this.preset_filters['report_id']==14) {
          this.filter_data.platform.value = -1
      }

      // Assign preset report_id, and from/to date fields to the store variables
      this.$store.dispatch('updateReportId',this.preset_filters['report_id']);
      this.$store.dispatch('updateFromYM',this.preset_filters['fromYM']);
      this.$store.dispatch('updateToYM',this.preset_filters['toYM']);

      // Set options for all filters and in the datastore
      this.rangeKey += 1;           // force re-render of the date-range component
      console.log('TitleReport Component mounted.');
    }
  }
</script>
<style>
.align-mid { align-items: center; }
.align-bot { align-items: flex-end; }
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
