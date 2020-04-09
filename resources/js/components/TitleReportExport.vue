<template>
  <div>
    <v-row>
      <v-col cols="1" sm="1">
        <v-btn color="pink" dark @click.stop="filter_drawer = !filter_drawer">Filter</v-btn>
      </v-col>
      <v-col cols="1" sm="1">
        <v-btn color="pink" dark @click.stop="column_drawer = !column_drawer">Columns</v-btn>
      </v-col>
    </v-row>
    <v-row>
      <v-btn color="green" type="button" @click="previewData">{{ preview_text }}</v-btn>
    </v-row>

    <v-navigation-drawer v-model="column_drawer" absolute temporary>
      <v-menu origin="center center" :close-on-content-click="false" transition="v-scale-transition" bottom>
        <template v-slot:activator="{ on }">
          <v-btn v-on="on">Show/Hide Columns</v-btn>
          <v-list dense>
            <v-list-item v-for="header in headers" :key="header.value">
              <v-checkbox :label="header.text" v-model="header.active" :value="header.active"
                          @change="onColumnChange(header)"></v-checkbox>
            </v-list-item>
          </v-list>
        </template>
      </v-menu>
    </v-navigation-drawer>

    <v-navigation-drawer v-model="filter_drawer" absolute temporary>
      <v-list dense>
        <v-list-item>
          <v-select :items='all_options.providers'
              v-if='all_filters.prov_id >= 0'
              v-model='filter_data.provider.value'
              @change="onFilterChange('providers')"
              label="Provider"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.platforms'
              v-if='all_filters.plat_id >= 0'
              v-model='filter_data.platform.value'
              @change="onFilterChange('platforms')"
              label="Platform"
              placeholder="Filter by Platform"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.institutions'
              v-if='all_filters.inst_id >= 0'
              v-model='filter_data.institution.value'
              @change="onFilterChange('institutions')"
              label="Institution"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.datatypes'
              v-if='all_filters.datatype_id >= 0'
              v-model='filter_data.datatype.value'
              @change="onFilterChange('datatypes')"
              label="Data Type"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.sectiontypes'
              v-if='all_filters.sectiontype_id >= 0'
              v-model='filter_data.sectiontype.value'
              @change="onFilterChange('sectiontypes')"
              label="SectionType"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.accesstypes'
              v-if='all_filters.accesstype_id >= 0'
              v-model='filter_data.accesstype.value'
              @change="onFilterChange('accesstypes')"
              label="Access Type"
              item-text="name"
              item-value="id"
           ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.accessmethods'
               v-if='all_filters.accessmethod_id >= 0'
               v-model='filter_data.accessmethod.value'
               @change="onFilterChange('accessmethods')"
               label="Access Type"
               item-text="name"
               item-value="id"
          ></v-select>
        </v-list-item>
      </v-list>
    </v-navigation-drawer>

    <div v-if="showPreview">
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
    </div>
    <v-row>
      <v-btn color="green" dark>Export</v-btn>
    </v-row>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: { },
    data () {
      return {
        showPreview: false,
        preview_text: 'Display Preview',
        // fromMenu: false,
        // toMenu: false,
        // YMFrom: '',
        // YMTo: '',
        master_id: 1,          // for TR report
        change_counter: 0,
        totalRecs: 0,
        filter_drawer: null,
        column_drawer: null,
        loading: true,
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
          datatype: { col:'datatype_id', act:'updateDataType', value:0 },
          sectiontype: { col:'sectiontype_id', act:'updateSectionType', value:0 },
          accesstype: { col:'accesstype_id', act:'updateAccessType', value:0 },
          accessmethod: { col:'accessmethod_id', act:'updateAccessMethod', value:0 },
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
    // watch: {
    //   YMFrom: function (newVal) {
    //       this.$store.dispatch('updateFromYM',newVal);
    //   },
    //   YMTo: function (newVal) {
    //       this.$store.dispatch('updateToYM',newVal);
    //   }
    // },
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
                  this.updateReportFilters(this.all_filters[theFilter.col]);
              }
          // Turning off a column...
          } else {
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
            // var filter = '';
            // if (typeof(this.filter_data[head.value]) != 'undefined') {    // filtered column?
            //     filter = this.filter_data[head.value].value;
            // }
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
              master_id: this.master_id,
          })
          .then( function(response) {
              for (var key in self.filter_data) {
                  var filter = self.filter_data[key];
                  if (typeof(response.data.filters[key]) != 'undefined') {
                      let action = filter.act+'Options';
                      self.$store.dispatch(action, response.data.filters[key]);
                  }
              }
          })
          .catch(error => {});
        },
    },
    computed: {
      //computed params to return pagination and settings
      params(nv) {
        return {
            preview: 100,
            master_id: this.master_id,
            YM_from: this.all_filters.YM_from,
            YM_to: this.all_filters.YM_to,
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

      ...mapGetters(['is_manager','is_viewer','all_filters','all_options']),
    },
    mounted() {
      // Turn off initial filter-state for "filterable" columns defaulted as not active
      this.headers.forEach(head => {
        if (typeof(this.filter_data[head.value]) != 'undefined') {    // filtered column?
            var theFilter = this.filter_data[head.value];
            var action = theFilter.act+'Filter';
            if (!head.active) this.$store.dispatch(action,-1);
        }
      });

      // Set From/To Yearmon based on current state
      // this.YMFrom = this.all_filters.YM_from;
      // this.YMTo = this.all_filters.YM_to;

      // Set options for all filters and in the datastore
      this.updateReportFilters();
      console.log('TitleReport Component mounted.');
    }
  }
</script>
<style>
</style>
