<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>

    <!-- Data table for data/harvest alerts -->
    <h2>Failed Harvest Alerts</h2>
    <v-data-table :headers="headers" :items="mutable_alerts" item-key="id" class="elevation-1" :hide-actions="true" :total-items="5" dense>
      <template v-slot:item="{ item }">
        <tr>
          <td width="10%" v-if="is_admin" style="vertical-align:middle">
            <v-select :items="statusvals" v-model="item.status" value="item.status" dense
                      @change="updateStatus(item)"
            ></v-select>
          </td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.prov_name }}</td>
          <td>{{ item.inst_name }}</td>
		  <td>{{ item.report_name }}</td>
          <td v-if="(item.updated_at)">{{ item.updated_at.substr(0,10) }}</td>
          <td v-else> </td>
		  <td><a :href="item.detail_url">Harvest details</a></td>
        </tr>
      </template>
    </v-data-table>
	<a href="#">See all failed harvests</a>
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
           },
    data () {
      return {
        headers: [
          { text: 'Year-Month', value: 'yearmon' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Institution', value: 'inst_name' },
		  { text: 'Report', value: 'report_name' },
          { text: 'Last Updated', value: 'updated_at' },
		  { text: '', value: '' },
        ],
        form: new window.Form({
            severity: '',
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
      }
    },
    methods:{
        formSubmit() {
            this.success = '';
            this.failure = '';

            this.showForm='';
        },
        hideForm() {
            this.showForm = '';
        },
    },
    computed: {
      ...mapGetters(['is_admin'])
    },
    mounted() {
      // per-alert select options don't use "ALL"
      this.statusvals = this.statuses.slice(1);
      console.log('AlertData Component mounted.');
    }
  }
</script>

<style>
.good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
