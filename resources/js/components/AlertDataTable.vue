<template>
  <div>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
    <!-- Container for system alerts -->
    <v-row v-if="is_admin">
      <v-col v-if="mutable_sysalerts.length>0"><h3>CC+ System-wide Alerts</h3></v-col>
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
            <v-select :items="severities" v-model="form.severity" value="form.severity" label="Severity" outlined>
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
              <td>{{ alert.severity }}</td>
              <td>{{ alert.text }}</td>
              <td><v-btn small color="primary" @click="editSys(alert.id)">edit</v-btn></td>
              <td><v-btn small class='btn btn-danger' type="button" @click="deleteSys(alert.id)">delete</v-btn></td>
            </tr>
          </tbody>
        </template>
      </v-simple-table>
      <p>&nbsp;</p>
    </div>

    <!-- Data table for data/harvest alerts -->
    <h3>Harvest / Data Alerts</h3>
    <v-data-table :headers="headers" :items="mutable_alerts" item-key="id" class="elevation-1" dense>
      <template v-slot:item="{ item }">
        <tr>
          <td width="10%" v-if="is_admin" style="vertical-align:middle">
            <v-select :items="statusvals" v-model="item.status" value="item.status" dense
                      @change="updateStatus(item)"
            ></v-select>
          </td>
          <td width="10%" v-else>{{ item.status }}</td>
          <td>{{ item.yearmon }}</td>
          <td><a :href="item.detail_url">{{ item.detail_txt }}</a></td>
          <td>{{ item.report_name }}</td>
          <td>{{ item.prov_name }}</td>
          <td>{{ item.inst_name }}</td>
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
        createSys() {
            this.failure = '';
            this.success = '';
            this.showForm = "create";
            this.form.severity = 'Info';
            this.form.is_active = 1;
            this.form.text = '';
        },
        editSys(alertid) {
            this.failure = '';
            this.success = '';
            this.showForm = "edit";
            this.current_sysalert= this.mutable_sysalerts[this.mutable_sysalerts.findIndex(a=> a.id == alertid)];
            this.form.severity = this.current_sysalert.severity;
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
                  this.mutable_sysalerts.splice(this.mutable_sysalerts.findIndex(a=> a.id == alert.id),1);
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
                            // Update mutable_sysalerts record with newly saved values...
                            var idx = this.mutable_sysalerts.findIndex(a => a.id == this.current_sysalert.id);
                            Object.assign(this.mutable_sysalerts[idx], response.alert);
                        } else {
                            this.failure = response.msg;
                        }
                    });
            } else if (this.showForm == 'create') {
                this.form.post('/systemalerts')
                    .then( (response) => {
                        if (response.result) {
                            self.failure = '';
                            this.mutable_sysalerts.push(response.alert);
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
                  text: "This action is no reversible and underlying causes may cause the alert to be recreated.",
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
