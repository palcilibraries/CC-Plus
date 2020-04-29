<template>
  <div>
    <date-range :minym="minYM" :maxym="maxYM"
                :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM"
                :key="rangeKey"
    ></date-range>
    <span><strong>Show/Hide Columns</strong></span>
    <v-row no-gutters>
      <v-col class="ma-2" v-for="header in headers" :key="header.value">
        <v-checkbox :label="header.text" v-model="header.active" :value="header.active"
                    @change="onColumnChange(header)"></v-checkbox>
      </v-col>
    </v-row>
    <span v-if="showFilters"><strong>Filters</strong></span>
    <v-row v-if="showFilters" no-gutters>
      <v-col v-if='filter_data["provider"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.providers'
                  v-model='filter_data.provider.value'
                  @change="onFilterChange('provider')"
                  label="Provider"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["platform"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.platforms'
                  v-model='filter_data.platform.value'
                  @change="onFilterChange('platform')"
                  label="Platform"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["institution"].value >= 0 && filterInst' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.institutions'
                  v-model='filter_data.institution.value'
                  @change="onFilterChange('institution')"
                  label="Institution"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["institutiongroup"].value >= 0 && !filterInst' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.institutiongroups'
                  v-model='filter_data.institutiongroup.value'
                  @change="onFilterChange('institutiongroup')"
                  label="Institution Group"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["datatype"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.datatypes'
                  v-model='filter_data.datatype.value'
                  @change="onFilterChange('datatype')"
                  label="Data Type"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["sectiontype"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.sectiontypes'
                  v-model='filter_data.sectiontype.value'
                  @change="onFilterChange('sectiontype')"
                  label="SectionType"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["accesstype"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.accesstypes'
                  v-model='filter_data.accesstype.value'
                  @change="onFilterChange('accesstype')"
                  label="Access Type"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["accessmethod"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.accessmethods'
                  v-model='filter_data.accessmethod.value'
                  @change="onFilterChange('accessmethod')"
                  label="Access Method"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
    </v-row>
    <v-row>
      <v-col class="pa-2" cols="4" sm="2">
        <v-btn class='btn' small type="button" color="primary" @click="previewData">{{ preview_text }}</v-btn>
      </v-col>
      <v-col class="pa-2" cols="4" sm="2">
        <v-btn class='btn' small type="button" color="primary" @click="showForm">Save Configuration</v-btn>
      </v-col>
      <v-col class="pa-2" cols="4" sm="2">
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
        <div v-if="form.title=='' && saved_reports.length>0">
          <h5 v-if="form.save_id!=input_save_id">Overwrite an existing saved configuration</h5>
          <h5 v-else>Overwrite an existing saved configuration, OR</h5>
          <v-row>
            <v-col class="pa-2" cols="8" sm="4">
              <input id="title" name="title" value="" type="hidden">
              <v-select :items='saved_reports'
                        v-model='form.save_id'
                        label="Saved Report"
                        item-text="title"
                        item-value="id"
              ></v-select>
            </v-col>
          </v-row>
        </div>
        <div v-if="form.save_id==input_save_id">
          <h5>Create a new saved configuration</h5>
          <v-row v-if="form.save_id==0">
            <v-col class="pa-2" cols="8" sm="4">
              <input name="save_id" id="save_id" value=0 type="hidden">
              <v-text-field v-model="form.title" label="Name" outlined></v-text-field>
            </v-col>
          </v-row>
        </div>
        <v-row>
          <v-col class="pa-2" cols="4" sm="2">
            <v-btn class='btn' small type="submit" color="green" :disabled="form.errors.any()">Save</v-btn>
          </v-col>
          <v-col class="pa-2" cols="4" sm="2">
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
            <template slot="headers" slot-scope="header">
              <td v-if="showColum(header.value)">{{ item[header.value] }}</td>
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
        saved_reports: { type:Array, default: () => [] },
        input_save_id: { type:Number, default: 0 },
    },
    data () {
      return {
        showPreview: false,
        showFilters: false,
        configForm: false,
        filterInst: true,   // T: filter-by-inst,  F: filter-by-inst-group
        preview_text: 'Display Preview',
        change_counter: 0,
        totalRecs: 0,
        filter_drawer: null,
        column_drawer: null,
        loading: true,
        minYM: '',
        maxYM: '',
        rangeKey: 1,
        pagination: {
            page: 1,
            itemsPerPage: 20,
            sortBy: [],
            sortDesc: [0],
            totalItems: 0
        },
        footer_props: {
            'items-per-page-options': [10, 20, 50, 100],
        },
        report_data: [],
        filter_data: {
          provider: { col:'prov_id', act:'updateProvider', value:0 },
          platform: { col:'plat_id', act:'updatePlatform', value:0 },
          institution: { col:'inst_id', act:'updateInstitution', value:0 },
          institutiongroup: { col:'institutiongroup_id', act:'updateInstGroup', value:0 },
          datatype: { col:'datatype_id', act:'updateDataType', value: -1 },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value: -1 },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value: -1 },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value: -1 },
        },
        headers: this.columns,
        success: '',
        failure: '',
        form: new window.Form({
            title: '',
            save_id: this.input_save_id,
        })
      }
    },
    watch: {
      filter_data: {
        handler() {
          this.showFilters = this.filtersEnabled;
        },
        deep: true
      },
    },
    methods: {
        previewData (event) {
            if (!this.showPreview) {
                this.showPreview = true;
                this.preview_text = 'Refresh Preview';
            }
            this.getData().then(data => {
                  this.report_data = data.items;
            });
        },
        showColumn(col) {
            return this.headers.find(h => h.value === col).active
        },
        onColumnChange(head) {
          if (typeof(this.filter_data[head.value]) != 'undefined') {    // column has a filter?
              var hasFilter=true;
              var theFilter = this.filter_data[head.value];
              var action = theFilter.act+'Filter';
          } else {
              var hasFilter=false;
          }
          // Turning on a column...
          if (head.active) {
              if (hasFilter) {
                  // Set filter to "all" unless this is for institution and we're filtering by inst-group
                  if (head.value!='institution' || (head.value!='institution' && this.filterInst)) {
                      this.filter_data[head.value].value = 0;
                  }
                  this.$store.dispatch(action,0);
                  // Update options for this column in the datastore
                  this.updateReportFilters({[theFilter.col]:0 });
              }
          // Turning off a column...
          } else {
              // Remove the filter from the list
              this.filter_data[head.value].value = -1;
              // If column was actively filtering, rebuild all filtering options
              if (hasFilter && this.all_filters[theFilter.col]>0) {
                  // Update filter in store to -1 to remove column from data queries/reloads
                  this.$store.dispatch(action,-1);
                  // Update options for all columns
                  this.updateReportFilters();
              }
          }
        },
        onFilterChange (target) {
          let method = this.filter_data[target].act+'Filter';
          // Store the new filter value
          this.$store.dispatch(method, this.filter_data[target].value);
          // Update options for active/displayed filterable columns
          this.updateReportFilters();
        },
        getData () {
          this.loading = true;
          //copy current params to modify
          let params = this.params;
          params['filters'] = JSON.stringify(this.all_filters);
          let _cols = {};
          this.headers.forEach(head => {
            var fval = (typeof(this.filter_data[head.value])=='undefined') ? '' : this.filter_data[head.value].value;
            _cols[head.value] = {active: head.active, limit: fval};
          })
          params['columns'] = JSON.stringify(_cols);

          return new Promise((resolve, reject) => {
            axios.get("/usage-report-data?"+Object.keys(params).map(key => key+'='+params[key]).join('&'))
                            .then((response) => {
                const { page, itemsPerPage } = this.pagination;
                let items = response.data.usage;
                this.totalRecs = response.data.usage.length;
                resolve({items});
                this.loading = false;
            })
            .catch(err => console.log(err));
          });
        },
        updateReportFilters (arg) {
          // var targets = (typeof arg !== 'undefined') ? arg : this.all_filters;
          // targets['report_id'] = this.all_filters.report_id;
          var self = this;
          axios.post('/update-report-filters', {
              // filters: targets,
              filters: this.all_filters,
          })
          .then( function(response) {
              for (var key in self.filter_data) {
                  var filter = self.filter_data[key];
                  if (typeof(response.data.filters[key]) != 'undefined') {
                      let action = filter.act+'Options';
                      self.$store.dispatch(action, response.data.filters[key]);
                  }
              }
              self.minYM = response.data.bounds.YM_min;
              self.maxYM = response.data.bounds.YM_max;
          })
          .catch(error => {});
        },
        showForm (event) {
            this.configForm = true;
        },
        hideForm (event) {
            this.configForm = false;
        },
        saveConfig() {
            if (this.form.title=='' && this.form.save_id==0) {
                this.failure = 'A name is required to save the configuration';
                return;
            }
            let _cols = {};
            this.headers.forEach(head => {
              var fval = (typeof(this.filter_data[head.value])=='undefined') ? '' : this.filter_data[head.value].value;
              _cols[head.value] = {active: head.active, limit: fval};
            })
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
            axios.post('/save-report-config', {
                title: this.form.title,
                save_id: this.form.save_id,
                report_id: this.all_filters.report_id,
                months: num_months,
                fields: JSON.stringify(_cols),
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
        },
    },
    computed: {
      ...mapGetters(['is_manager', 'is_viewer', 'all_filters', 'all_options', 'filter_by_fromYM', 'filter_by_toYM']),
      filtersEnabled() { // Returns T/F if there are active filters
          for (let key in this.filter_data) {
            if (this.filter_data[key].value >= 0) return true;
          }
          return false;
      },
      months(nv) {  // Returns an array of yearmon strings based on From/To in the store
        let _mons = new Array();
        var from_parts = this.filter_by_fromYM.split("-");
        var to_parts = this.filter_by_toYM.split("-");
        var fromDate = new Date(+from_parts[0], from_parts[1] - 1, 1);
        var toDate = new Date(+to_parts[0], to_parts[1] - 1, 1);

        for (var m = fromDate; m <= toDate; m.setMonth(m.getMonth() + 1)) {
            _mons.push(m.toISOString().substring(0,7));
        }
        return _mons;
      },
      params(nv) {  // Computed params to return pagination and settings
        return {
            preview: 100,
            report_id: this.all_filters.report_id,
            ...this.pagination
        };
      },
      filteredHeaders() {
        return this.headers.filter(h => h.active)
      },
      showCounts() {
          return this.showColumn('total_item_investigations') || this.showColumn('total_item_requests') ||
                 this.showColumn('unique_item_investigations') || this.showColumn('unique_item_requests') ||
                 this.showColumn('unique_title_investigations') || this.showColumn('unique_title_requests') ||
                 this.showColumn('limit_exceeded') || this.showColumn('no_license');
      },
    },
    mounted() {
      // Turn off initial filter-state for inactive "filterable" columns
      this.headers.forEach(head => {
        if (typeof(this.filter_data[head.value]) != 'undefined') {    // filtered column?
            var theFilter = this.filter_data[head.value];
            var action = theFilter.act+'Filter';
            if (!head.active) {
                theFilter.value = -1;
                this.$store.dispatch(action,-1);
            }
        }
      });

      // Assign preset filter values
      for (let [key, data] of Object.entries(this.filter_data)) {
          if (this.preset_filters[data.col]>0) {
              let filt = data.act+'Filter';
              this.$store.dispatch(filt,this.preset_filters[data.col]);
              data.value = this.preset_filters[data.col];
          }
      }
      if (this.preset_filters['institutiongroup_id']>0)  this.filterInst = false;

      // Manually disable platform filtering for platform reports
      if (this.preset_filters['report_id']==3 || this.preset_filters['report_id']==14) {
          this.filter_data.platform.value = -1
      }

      // Assign preset report_id, and from/to date fields to the store variables
      this.$store.dispatch('updateReportId',this.preset_filters['report_id']);
      this.$store.dispatch('updateFromYM',this.preset_filters['fromYM']);
      this.$store.dispatch('updateToYM',this.preset_filters['toYM']);

      // Set options for all filters and in the datastore
      this.updateReportFilters();
      this.showFilters = this.filtersEnabled;
      this.rangeKey += 1;           // force re-render of the date-range component
      console.log('TitleReport Component mounted.');
    }
  }
</script>
<style>
</style>
