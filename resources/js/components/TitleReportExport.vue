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
    <span><strong>Filters</strong></span>
    <v-row no-gutters>
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
                  placeholder="Filter by Platform"
                  item-text="name"
                  item-value="id"
        ></v-select>
      </v-col>
      <v-col v-if='filter_data["institution"].value >= 0' class="ma-2" cols="1" sm="1">
        <v-select :items='all_options.institutions'
                  v-model='filter_data.institution.value'
                  @change="onFilterChange('institution')"
                  label="Institution"
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
      <v-btn color="green" type="button" @click="previewData">{{ preview_text }}</v-btn>
    </v-row>
    <v-container v-if="showPreview" fluid>
      <v-data-table :headers="filteredHeaders" :items="filteredItems"
                    :loading="loading" :footer-props="footer_props" dense class="elevation-1">
        <template slot="filteredItems" slot-scope="item">
          <tr>
            <td v-if="showColumn('Title')">{{ item.Title }}</td>
            <td v-if="showColumn('provider')">{{ item.provider }}</td>
            <td v-if="showColumn('publisher')">{{ item.publisher }}</td>
            <td v-if="showColumn('platform')">{{ item.platform }}</td>
            <td v-if="showColumn('institution')">{{ item.institution }}</td>
            <td v-if="showColumn('datatype')">{{ item.datatype }}</td>
            <td v-if="showColumn('sectiontype')">{{ item.sectiontype }}</td>
            <td v-if="showColumn('accesstype')">{{ item.accesstype }}</td>
            <td v-if="showColumn('accessmethod')">{{ item.accessmethod }}</td>
            <td v-if="showColumn('YOP')">{{ item.YOP }}</td>
            <td v-if="showColumn('ISBN')">{{ item.ISBN }}</td>
            <td v-if="showColumn('ISSN')">{{ item.ISSN }}</td>
            <td v-if="showColumn('eISSN')">{{ item.eISSN }}</td>
            <td v-if="showColumn('URI')">{{ item.URI }}</td>
            <td v-if="showColumn('DOI')">{{ item.DOI }}</td>
            <td v-if="showColumn('PropID')">{{ item.PropID }}</td>
            <td v-if="showColumn('total_item_investigations')">{{ item.total_item_investigations }}</td>
            <td v-if="showColumn('total_item_requests')">{{ item.total_item_requests }}</td>
            <td v-if="showColumn('unique_item_investigations')">{{ item.unique_item_investigations }}</td>
            <td v-if="showColumn('unique_item_requests')">{{ item.unique_item_requests }}</td>
            <td v-if="showColumn('unique_title_investigations')">{{ item.unique_title_investigations }}</td>
            <td v-if="showColumn('unique_title_requests')">{{ item.unique_title_requests }}</td>
            <td v-if="showColumn('limit_exceeded')">{{ item.limit_exceeded }}</td>
            <td v-if="showColumn('no_license')">{{ item.no_license }}</td>
          </tr>
        </template>
      </v-data-table>
    </v-container>
    <v-row>
      <v-btn color="green" dark>Export</v-btn>
    </v-row>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
        input_filters: { type:Object, default: () => {} },
        // input_filters: { type:Array, default: () => [] },
    },
    data () {
      return {
        showPreview: false,
        preview_text: 'Display Preview',
        report_id: 1, // default to TR master
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
            sortBy: ["Title"],
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
          datatype: { col:'datatype_id', act:'updateDataType', value: -1 },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value: -1 },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value: -1 },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value: -1 },
        },
        headers: [
          { text:'Title', value:'Title', active:true, reload: true },
          { text:'Provider', value:'provider', active:true, reload: true },
          { text:'Publisher', value:'publisher', active:false, reload: true },
          { text:'Platform', value:'platform', active:true, reload: true },
          { text:'Institution', value:'institution', active:true, reload: true },
          { text:'Data Type', value:'datatype', active:false, reload: true },
          { text:'Section Type', value:'sectiontype', active:false, reload: true },
          { text:'Access Type', value:'accesstype', active:false, reload: true },
          { text:'Access Method', value:'accessmethod', active:false, reload: true },
          { text:'Year of Publication', value:'YOP', active:false, reload: true },
          { text:'ISBN', value:'ISBN', active:false, reload: true },
          { text:'ISSN', value:'ISSN', active:false, reload: true },
          { text:'eISSN', value:'eISSN', active:false, reload: true },
          { text:'URI', value:'URI', active:false, reload: true },
          { text:'DOI', value:'DOI', active:false, reload: true },
          { text:'Proprietary ID', value:'PropID', active:false, reload: true },
          { text:'Total Item Investigations', value:'total_item_investigations', active:false, reload: false },
          { text:'Total Item Requests', value:'total_item_requests', active:true, reload: false },
          { text:'Unique Item Investigations', value:'unique_item_investigations', active:false, reload: false },
          { text:'Unique Item Requests', value:'unique_item_requests', active:false, reload: false },
          { text:'Unique Title Investigations', value:'unique_title_investigations', active:false, reload: false },
          { text:'Unique Title Requests', value:'unique_title_requests', active:false, reload: false },
          { text:'Limit Exceeded', value:'limit_exceeded', active:false, reload: false },
          { text:'No License', value:'no_license', active:false, reload: false },
        ],
      }
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
                  // Set filter to "all"
                  this.filter_data[head.value].value = 0;
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
          let columns = {};
          this.headers.forEach(head => {
            var filter = (typeof(this.filter_data[head.value])=='undefined') ? '' : this.filter_data[head.value].value;
            columns[head.value] = {active: head.active, limit: filter};
          })
          params['columns'] = JSON.stringify(columns);

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
          var targets = (typeof arg !== 'undefined') ? arg : this.all_filters;
          var self = this;
          axios.post('/update-report-filters', {
              filters: targets,
              report_id: this.report_id,
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
    },
    computed: {
      ...mapGetters(['is_manager','is_viewer','all_filters','all_options', 'filter_by_fromYM', 'filter_by_toYM']),
      // Returns an array of yearmon strings based on From/To in the store
      months(nv) {
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
      //computed params to return pagination and settings
      params(nv) {
        return {
            preview: 100,
            report_id: this.report_id,
            YM_from: this.all_filters.fromYM,
            YM_to: this.all_filters.toYM,
            ...this.pagination
        };
      },
      filteredHeaders() {
        return this.headers.filter(h => h.active)
      },
      filteredItems() {
        // Filtering matching report rows
        var self = this;
        let filtered_usage = this.report_data.filter(function(row) {
          for (let [key, value] of Object.entries(self.all_filters)) {
            if (value>0) {
              if (row[key] === undefined || row[key] != value) return false;
            }
          }
          return true;
        });

        // hide columns that are currently off
        return filtered_usage.map(item => {
          let filtered = Object.assign({}, item)
          this.headers.forEach(header => {
            if (!header.active) delete filtered[header.value]
          });
          return filtered;
        });
      },
      showCounts() {
          return this.showColumn('total_item_investigations') || this.showColumn('total_item_requests') ||
                 this.showColumn('unique_item_investigations') || this.showColumn('unique_item_requests') ||
                 this.showColumn('unique_title_investigations') || this.showColumn('unique_title_requests') ||
                 this.showColumn('limit_exceeded') || this.showColumn('no_license');
      },
    },
    mounted() {
// --->>    NOW: need a way to set/get/pass-in which columns to default on+off
// --->>         based on store.report_id  ...
// --->>         may be something to be added to updateReportFilters
      // If we got filters as a valid prop, push into the state
      if (typeof(this.input_filters) != 'undefined') {
          if (Object.keys(this.input_filters).length >= 11) {
              this.$store.dispatch('updateAllFilters',this.input_filters);
          }
      } else {
          // Turn off initial filter-state for "filterable" columns defaulted as not active
          this.headers.forEach(head => {
            if (typeof(this.filter_data[head.value]) != 'undefined') {    // filtered column?
                var theFilter = this.filter_data[head.value];
                var action = theFilter.act+'Filter';
                if (!head.active) this.$store.dispatch(action,-1);
            }
          });
      }

      // Set options for all filters and in the datastore
      this.updateReportFilters();
      this.rangeKey += 1;           // force re-render of the date-range component
      console.log('TitleReport Component mounted.');
    }
  }
</script>
<style>
</style>
