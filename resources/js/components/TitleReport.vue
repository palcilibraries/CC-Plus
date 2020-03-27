<template>
  <div>
    <v-row>
      <v-btn color="pink" dark @click.stop="filter_drawer = !filter_drawer">Filter</v-btn>
      <v-btn color="pink" dark @click.stop="column_drawer = !column_drawer">Columns</v-btn>
    </v-row>

    <v-navigation-drawer v-model="filter_drawer" absolute temporary>

        <!-- <v-list dense>
          <v-list-item v-for="header in headers" :key="header.value">
            <v-checkbox :label="header.text" v-model="header.selected" :value="header.selected"
                        @change="onColumnChange"></v-checkbox>
          </v-list-item>
        </v-list>
 -->

      <v-list dense>
        <v-list-item>
          <v-select :items='all_options.providers'
              v-if='all_filters.prov_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updateProviderFilter')"
              label="Provider"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.platforms'
              v-if='all_filters.plat_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updatePlatformFilter')"
              label="Plaform"
              placeholder="Filter by Platform"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.institutions'
              v-if='all_filters.inst_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updateInstitutionFilter')"
              label="Institution"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.datatypes'
              v-if='all_filters.datatype_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updateDataTypeFilter')"
              label="Data Type"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.sectiontypes'
              v-if='all_filters.sectiontype_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updateSectionTypeFilter')"
              label="SectionType"
              item-text="name"
              item-value="id"
          ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.accesstypes'
              v-if='all_filters.accesstype_id >= 0'
              v-model='filter_id'
              @change="onFilterChange('updateAccessTypeFilter')"
              label="Access Type"
              item-text="name"
              item-value="id"
           ></v-select>
        </v-list-item>
        <v-list-item>
          <v-select :items='all_options.accessmethods'
               v-if='all_filters.accessmethod_id >= 0'
               v-model='filter_id'
               @change="onFilterChange('updateAccessTypeFilter')"
               label="Access Type"
               item-text="name"
               item-value="id"
          ></v-select>
        </v-list-item>
      </v-list>
    </v-navigation-drawer>

    <v-navigation-drawer v-model="column_drawer" absolute temporary>
      <v-menu origin="center center" :close-on-content-click="false" transition="v-scale-transition" bottom>
        <template v-slot:activator="{ on }">
          <v-btn v-on="on">Show/Hide Columns</v-btn>
          <v-list dense>
            <v-list-item v-for="header in headers" :key="header.value">
              <v-checkbox :label="header.text" v-model="header.selected" :value="header.selected"
                          @change="onColumnChange(header)"></v-checkbox>
            </v-list-item>
          </v-list>
        </template>
      </v-menu>
    </v-navigation-drawer>

    <v-data-table :headers="filteredHeaders" :items='filteredItems' :loading="loading" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td v-if="showColumn('name')">{{ item.title.Title }}</td>
          <td v-if="showColumn('prov_id')">{{ item.provider.name }}</td>
          <td v-if="showColumn('publisher_id')">{{ item.publisher.name }}</td>
          <td v-if="showColumn('plat_id')">{{ item.platform.name }}</td>
          <td v-if="showColumn('inst_id')">{{ item.institution.name }}</td>
          <td v-if="showColumn('datatype_id')">{{ item.datatype.name }}</td>
          <td v-if="showColumn('sectiontype_id')">{{ item.sectiontype.name }}</td>
          <td v-if="showColumn('accesstype_id')">{{ item.accesstype.name }}</td>
          <td v-if="showColumn('accessmethod_id')">{{ item.accessmethod.name }}</td>
          <td v-if="showColumn('YOP')">{{ item.YOP }}</td>
          <td v-if="showColumn('pub_date')">{{ item.title.pub_date }}</td>
          <td v-if="showColumn('article_version')">{{ item.title.article_version }}</td>
          <td v-if="showColumn('ISBN')">{{ item.title.ISBN }}</td>
          <td v-if="showColumn('ISSN')">{{ item.title.ISSN }}</td>
          <td v-if="showColumn('eISSN')">{{ item.title.eISSN }}</td>
          <td v-if="showColumn('URI')">{{ item.title.URI }}</td>
          <td v-if="showColumn('DOI')">{{ item.title.DOI }}</td>
          <td v-if="showColumn('PropID')">{{ item.title.PropID }}</td>
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
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
        institutiongroups: { type:Array, default: () => [] },
    },
    data () {
      return {
        // criteria: {},
        filter_drawer: null,
        column_drawer: null,
        loading: true,
        pagination: {},
        filter_id: { type:Number, default:0 },
        filters: [
          { name: 'providers', act: 'updateProvider' },
          { name: 'platforms', act: 'updatePlatform' },
          { name: 'institutions', act: 'updateInstitution' },
          { name: 'datatypes', act: 'updateDataType' },
          { name: 'sectiontypes', act: 'updateSectionType' },
          { name: 'accesstypes', act: 'updateAccessType' },
          { name: 'accessmethods', act: 'updateAccessMethod' }
        ],
        headers: [
          { text: 'Title', value: 'name', selected: true, act: '' },
          { text: 'Provider', value: 'prov_id', selected: true, act: 'updateProvider' },
          { text: 'Publisher', value: 'publisher_id', selected: false, act: '' },
          { text: 'Plaftorm', value: 'plat_id', selected: false, act: 'updatePlatform' },
          { text: 'Institution', value: 'inst_id', selected: true, act: 'updateInstitution' },
          { text: 'Data Type', value: 'datatype_id', selected: false, act: 'updateDataType' },
          { text: 'Section Type', value: 'sectiontype_id', selected: false, act: 'updateSectionType' },
          { text: 'Access Type', value: 'accesstype_id', selected: false, act: 'updateAccessType' },
          { text: 'Access Method', value: 'accessmethod_id', selected: false, act: 'updateAccessMethod' },
          { text: 'Year of Publication', value: 'YOP', selected: false, act: '' },
          { text: 'Publication Date', value: 'pub_date', selected: false, act: '' },
          { text: 'Article Version', value: 'article_version', selected: false, act: '' },
          { text: 'ISBN', value: 'ISBN', selected: false, act: '' },
          { text: 'ISSN', value: 'ISSN', selected: false, act: '' },
          { text: 'eISSN', value: 'eISSN', selected: false, act: '' },
          { text: 'URI', value: 'URI', selected: false, act: '' },
          { text: 'DOI', value: 'DOI', selected: false, act: '' },
          { text: 'Proprietary ID', value: 'PropID', selected: false, act: '' },
          { text: 'Total Item Investigations', value: 'total_item_investigations', selected: false, act: '' },
          { text: 'Total Item Requests', value: 'total_item_requests', selected: true, act: '' },
          { text: 'Unique Item Investigations', value: 'unique_item_investigations', selected: false, act: '' },
          { text: 'Unique Item Requests', value: 'unique_item_requests', selected: false, act: '' },
          { text: 'Unique Title Investigations', value: 'unique_title_investigations', selected: false, act: '' },
          { text: 'Unique Title Requests', value: 'unique_title_requests', selected: false, act: '' },
          { text: 'Limit Exceeded', value: 'limit_exceeded', selected:false, act: '' },
          { text: 'No License', value: 'no_license', selected: false, act: '' },
        ],
      }
    },
    methods: {
        showColumn(col) {
            return this.headers.find(h => h.value === col).selected
        },
        onColumnChange(head) {
          if (head.act=='') return;
          var action = head.act+'Filter';
          if (head.selected) {
              // Turning on a column should not reset the data
             this.$store.dispatch(action,0);
          } else {
             // Turning off a column that is actively filtering should reset data and other options ??
             this.$store.dispatch(action,-1);
             // axios.post('/usage-report-data', {
             //       filters: self.all_filters,
             //       master_id: self.master_id,
             // })
             // .then( function(response) {
             //   self.$store.dispatch('updateReportData',response.data.usage);
             // })
             // .catch(error => {});
          }
        },
        onFilterChange (method) {
          // Store the new filter value
          this.$store.dispatch(method, this.filter_id);
          // Update options for active filters
          var self = this;
          axios.post('/update-report-filters', {
              filters: self.all_filters,
              master_id: self.master_id,
          })
          .then( function(response) {
              self.filters.forEach(filter => {
                  if (typeof(response.data.filters[filter.name]) != 'undefined') {
                      let action = filter.act+'Options';
                      self.$store.dispatch(action, response.data.filters[filter.name]);
                  }
              })
          })
          .catch(error => {});
      },
    },
    computed: {
      filteredHeaders() {
        return this.headers.filter(h => h.selected)
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

        // hide columns that are not being shown right now
        return filtered_usage.map(item => {
          let filtered = Object.assign({}, item)
          this.headers.forEach(header => {
            if (!header.selected) delete filtered[header.value]
          })
          return filtered
        })

      },

      ...mapGetters(['is_manager','is_viewer','all_filters','all_options','master_id','report_data']),
    },
    // watch: {
    //   pagination: {
    //     handler() {
    //       this.getDataFromApi()
    //         .then(data => {
    //         this.items = this.report_data;
    //         this.totalItems = data.total
    //       })
    //     },
    //     deep: true
    //   }
    // },
    mounted() {
      // Turn off initial filter-state for "filterable" columns defaulted to !header.selected
      this.headers.forEach(header => {
        if (header.act != '') {
            var action = header.act+'Filter';
            if (!header.selected) this.$store.dispatch(action,-1);
        }
      })
// -- Hard-wired for testing
this.$store.dispatch('updateFromYM', '2020-01');
this.$store.dispatch('updateToYM', '2020-01');
// --
      this.$store.dispatch('updateMasterId', 1);    // set to ID for TR
      // Get options for all filters and put in the store
      var self = this;
      axios.post('/update-report-filters', {
          filters: self.all_filters,
          master_id: self.master_id,
      })
      .then( function(response) {
          self.filters.forEach(filter => {
              if (typeof(response.data.filters[filter.name]) != 'undefined') {
                  let action = filter.act+'Options';
                  self.$store.dispatch(action, response.data.filters[filter.name]);
              }
          })
      })
      .catch(error => {});
      axios.post('/usage-report-data', {
            filters: self.all_filters,
            master_id: self.master_id,
      })
      .then( function(response) {
        self.$store.dispatch('updateReportData',response.data.usage);
      })
      .catch(error => {});
      console.log('TitleReport Component mounted.');
    }
  }
</script>
<style>
</style>
