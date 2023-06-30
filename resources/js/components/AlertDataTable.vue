<template>
  <div>
    <div class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </div>
    <!-- Container for system alerts -->
    <v-row v-if="is_admin">
      <v-col v-if="mutable_sysalerts.length>0"><h2>CC+ System-wide Alerts</h2></v-col>
      <v-col v-if="showForm==''">
        <v-btn small color="primary" @click="createSys">Add A System Alert</v-btn>
      </v-col>
    </v-row>
    <!-- System alert create/edit form -->
    <div v-if="showForm!=''">
      <form method="POST" action="" @submit.prevent="formSubmit" class="in-page-form"
            @keydown="form.errors.clear($event.target.name)">
        <v-row>
          <v-col cols="2">
            <v-switch v-model="form.is_active" label="Active?"></v-switch>
          </v-col>
          <v-col cols="2">
            <v-select :items="severities" v-model="form.severity_id" value="form.severity_id" label="Severity"
                      item-value="id" item-text="name" outlined>
            </v-select>
          </v-col>
          <v-col cols="8">
            <v-text-field v-model="form.text" label="Message" outlined></v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Save</v-btn>
          </v-col>
          <v-col>
            <v-btn small type="button" @click="hideForm">Cancel</v-btn>
          </v-col>
        </v-row>
      </form>
    </div>

    <!-- Table of system alerts -->
    <div v-else-if="mutable_sysalerts.length>0">
      <v-simple-table fixed-header>
        <thead>
          <tr>
            <th class="text-left">Created</th>
            <th class="text-left">Status</th>
            <th class="text-left">Severity</th>
            <th class="text-left">Message</th>
            <th></th>
            <th></th>
          </tr>
        </thead>
        <template v-for="alert in mutable_sysalerts">
          <tbody>
            <tr>
              <td>{{ alert.created_at.substr(0,10) }}</td>
              <td v-if="alert.is_active">Active</td>
              <td v-else>Inactive</td>
              <td>{{ alert.severity.name }}</td>
              <td>{{ alert.text }}</td>
              <td v-if="is_admin"><v-btn small color="primary" @click="editSys(alert.id)">edit</v-btn></td>
              <td v-if="is_admin">
                <v-btn small class='btn btn-danger' type="button" @click="deleteSys(alert.id)">delete</v-btn>
              </td>
            </tr>
          </tbody>
        </template>
      </v-simple-table>
      <p>&nbsp;</p>
    </div>

    <!-- Data table for data/harvest alerts -->
    <h2>Harvest / Data Alerts</h2>
    <div class="d-flex pa-0 align-center">
      <div v-if="datesFromTo!='|'" class="x-box">
        <img src="/images/red-x-16.png" width="100%" alt="clear date range" @click="clearFilter('date_range')"/>&nbsp;
      </div>
      <date-range :minym="minYM" :maxym="maxYM" :ymfrom="filter_by_fromYM" :ymto="filter_by_toYM" :key="rangeKey"
      ></date-range>
    </div>
    <v-row class="d-flex pa-1" no-gutters>
      <v-col v-if='institutions.length>1' class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['inst'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('inst')"/>&nbsp;
        </div>
        <v-select :items="institutions" v-model="mutable_filters['inst']" @change="updateFilters()" multiple
                  label="Institution(s)"  item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['prov'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('prov')"/>&nbsp;
        </div>
        <v-select :items="providers" v-model="mutable_filters['prov']" @change="updateFilters()" multiple
                  label="Provider(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['rept'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('rept')"/>&nbsp;
        </div>
        <v-select :items="reports" v-model="mutable_filters['rept']" @change="updateFilters()" multiple
                  label="Report(s)" item-text="name" item-value="id"
        ></v-select>
      </v-col>
      <v-col class="d-flex px-2 align-center" cols="2" sm="2">
        <div v-if="mutable_filters['stat'].length>0" class="x-box">
          <img src="/images/red-x-16.png" width="100%" alt="clear filter" @click="clearFilter('stat')"/>&nbsp;
        </div>
        <v-select :items="statuses" v-model="mutable_filters['stat'][0]" @change="updateFilters()"
                  label="Status" item-text="name" item-value="name"
        ></v-select>
      </v-col>
    </v-row>
    <v-data-table :headers="headers" :items="mutable_alerts" item-key="id" class="elevation-1" dense
                  :options="mutable_options" @update:options="updateOptions" :key="dtKey">
      <template v-slot:item="{ item }">
        <tr>
          <td width="10%" v-if="is_admin" classs="align-center">
            <v-select :items="statusvals" v-model="item.status" value="item.status" :loading="loading" dense
                      @change="updateStatus(item)"
            ></v-select>
          </td>
          <td width="10%" v-else>{{ item.status }}</td>
          <td>{{ item.yearmon }}</td>
          <td><a :href="item.detail_url">{{ item.detail_txt }}</a></td>
          <td>{{ item.report_name }}</td>
          <td>{{ item.prov_name }}</td>
          <td v-if="is_admin">{{ item.inst_name }}</td>
          <td v-if="(item.updated_at)">{{ item.updated_at.substr(0,10) }}</td>
          <td v-else> </td>
          <td>{{ item.mod_by }}</td>
        </tr>
      </template>
    </v-data-table>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import { mapGetters } from 'vuex'
  export default {
    props: {
            alerts: { type:Array, default: () => [] },
            sysalerts: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            statuses: { type:Array, default: () => [] },
            severities: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => ({fromYM:null,toYM:null,inst:[],prov:[],rept:[],stat:[]}) },
           },
    data () {
      return {
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'Year-Month', value: 'yearmon' },
          { text: 'Condition', value: 'detail_txt' },
          { text: 'Report', value: 'report_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Last Updated', value: 'updated_at' },
          { text: 'Modified By ', value: 'mod_by' },
        ],
        form: new window.Form({
            severity_id: 0,
            is_active: '',
            text: '',
        }),
        showForm: '',
        success: '',
        failure: '',
        statusvals: [],
        current_sysalert: {},
        mutable_sysalerts: this.sysalerts,
        mutable_alerts: this.alerts,
        mutable_filters: this.filters,
        mutable_options: {},
        fstatus: [{ text: 'Active', value: 1}, {text: 'Silent', value: 0}],
        minYM: '',
        maxYM: '',
        dtKey: 1,
        rangeKey: 1,
        loading: true,
        table_options: {},
      }
    },
    watch: {
      datesFromTo: {
        handler() {
          // Changing date-range means we need to update state and reload records
          // (just not the FIRST change that happens on page load)
          if (this.rangeKey > 1 && this.all_filters.toYM != '' && this.all_filters.fromYM != '' &&
              this.all_filters.toYM != null && this.all_filters.fromYM != null) {
              this.mutable_filters['toYM'] = this.filter_by_toYM;
              this.mutable_filters['fromYM'] = this.filter_by_fromYM;
              this.$store.dispatch('updateAllFilters',this.mutable_filters);
              this.updateRecords();
          }
          this.rangeKey += 1;           // force re-render of the date-range component
        }
      },
    },
    methods:{
        updateFilters() {
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        clearFilter(filter) {
            if (filter == 'date_range') {
                this.mutable_filters['toYM'] = '';
                this.mutable_filters['fromYM'] = '';
                this.rangeKey += 1;           // force re-render of the date-range component
            } else {
                this.mutable_filters[filter] = [];
            }
            this.$store.dispatch('updateAllFilters',this.mutable_filters);
            this.updateRecords();
        },
        updateRecords() {
            this.loading = true;
            if (this.filter_by_toYM != null) this.mutable_filters['toYM'] = this.filter_by_toYM;
            if (this.filter_by_fromYM != null) this.mutable_filters['fromYM'] = this.filter_by_fromYM;
            let _filters = JSON.stringify(this.mutable_filters);
            axios.get("/alerts?json=1&filters="+_filters)
                 .then((response) => {
                     this.mutable_alerts = response.data.alerts;
                 })
                 .catch(err => console.log(err));
            this.loading = false;
        },
        updateOptions(options) {
            Object.keys(this.mutable_options).forEach( (key) =>  {
                if (options[key] !== this.mutable_options[key]) {
                    this.mutable_options[key] = options[key];
                }
            });
            this.$store.dispatch('updateDatatableOptions',this.mutable_options);
        },
        createSys() {
            this.failure = '';
            this.success = '';
            this.showForm = "create";
            this.form.severity_id = 0;
            this.form.is_active = 1;
            this.form.text = '';
        },
        editSys(alertid) {
            this.failure = '';
            this.success = '';
            this.showForm = "edit";
            this.current_sysalert= this.mutable_sysalerts[this.mutable_sysalerts.findIndex(a=> a.id == alertid)];
            this.form.severity_id = this.current_sysalert.severity_id;
            this.form.is_active = this.current_sysalert.is_active;
            this.form.text = this.current_sysalert.text;
        },
        deleteSys(alertid) {
            this.failure = '';
            this.success = '';
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/systemalerts/'+alertid)
                       .catch(error => {});
                  this.mutable_sysalerts.splice(this.mutable_sysalerts.findIndex(a=> a.id == alertid),1);
              }
            })
            .catch({});
        },
        formSubmit() {
            this.success = '';
            this.failure = '';
            if (this.showForm == 'edit') {
                this.form.is_active = (this.form.is_active) ? 1 : 0;
                this.form.patch('/systemalerts/'+this.current_sysalert.id)
                    .then((response) => {
                        if (response.result) {
                            // Update mutable_sysalerts record with newly saved and sorted values...
                            this.mutable_sysalerts = response.alerts;
                        } else {
                            this.failure = response.msg;
                        }
                    });
            } else if (this.showForm == 'create') {
                this.form.post('/systemalerts')
                    .then( (response) => {
                        if (response.result) {
                            self.failure = '';
                            this.mutable_sysalerts = response.alerts;
                        } else {
                            self.success = '';
                            self.failure = response.data.msg;
                        }
                    });
            }
            this.showForm='';
        },
        hideForm() {
            this.showForm = '';
        },
        updateStatus(alert) {
            if (alert.status == 'Delete') {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "This action is not reversible and underlying causes may cause the alert to be recreated.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/alerts/'+alert.id)
                           .then( (response) => {
                               if (response.data.result) {
                                   self.failure = '';
                                   self.success = response.data.msg;
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                       this.mutable_alerts.splice(this.mutable_alerts.findIndex(a=> a.id == alert.id),1);
                  }
                })
                .catch({});
            } else {
                axios.post('/update-alert-status', {
                    id: alert.id,
                    status: alert.status
                })
                .catch(error => {});
            }
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'filter_by_fromYM', 'filter_by_toYM', 'all_filters', 'datatable_options']),
      datesFromTo() {
        return this.filter_by_fromYM+'|'+this.filter_by_toYM;
      },
    },
    beforeCreate() {
      // Load existing store data
      this.$store.commit('initialiseStore');
    },
    beforeMount() {
        // Set page name in the store
        this.$store.dispatch('updatePageName','alerts');
	},
    mounted() {
      // Apply any defined prop-based filters (and overwrite existing store values)
      var count = 0;
      Object.assign(this.mutable_filters, this.all_filters);
      Object.keys(this.filters).forEach( (key) =>  {
        if (this.filters[key] != null) {
          if (key == 'fromYM' || key == 'toYM') {
            count++;
            this.mutable_filters[key] = this.filters[key];
          } else if (this.filters[key].length>0) {
            count++;
            this.mutable_filters[key] = this.filters[key];
          }
        }
      });

      // Set datatable options with store-values
      Object.assign(this.mutable_options, this.datatable_options);

      // Setup date-range filters
      if (typeof(this.bounds[0]) != 'undefined') {
        this.minYM = this.bounds[0].YM_min;
        this.maxYM = this.bounds[0].YM_max;
      }

      // per-alert select options don't use "ALL"
      this.statusvals = this.statuses.slice(1);

      // Update store and apply filters now that they're set
      if (count>0) this.$store.dispatch('updateAllFilters',this.mutable_filters);
      this.updateRecords();
      this.dtKey += 1;           // force re-render of the datatable

      // Subscribe to store updates
      this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });

      console.log('AlertData Component mounted.');
    }
  }
</script>

<style>
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
