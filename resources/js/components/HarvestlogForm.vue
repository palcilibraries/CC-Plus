<template>
  <v-container fluid>
    <v-row no-gutters>
      <v-col><h3 class="section-title">Harvest Details</h3></v-col>
    </v-row>
    <v-row>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Institution</v-col>
      <v-col cols="4" sm="2">{{ mutable_harvest.sushi_setting.institution.name }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Provider</v-col>
      <v-col cols="4" sm="2">{{ mutable_harvest.sushi_setting.provider.name }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Report</v-col>
      <v-col cols="4" sm="2">{{ mutable_harvest.report.name }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Usage Month</v-col>
      <v-col cols="4" sm="2">{{ mutable_harvest.yearmon }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Attempts</v-col>
      <v-col cols="2" sm="1">{{ mutable_harvest.attempts }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Status</v-col>
      <v-col cols="2" sm="1">{{ mutable_harvest.status }}</v-col>
    </v-row>
    <div v-if="(is_manager || is_admin)" class="d-flex ma-2 pa-0">
      <!-- Some statuses cannot be changed -->
      <v-row v-if="!(status_fixed.includes(mutable_harvest.status))" no-gutters class="d-flex align-mid">
        <v-col cols="2" sm="2">
          <v-select :items="status_canset" v-model="new_status" label="Modify Status" dense @change="updateStatus()">
          </v-select>
        </v-col>
        <v-col cols="1" sm="1">&nbsp;</v-col>
        <v-col cols="1" sm="1">
          <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_harvest.id)">Delete</v-btn>
        </v-col>
      </v-row>
    </div>
  </v-container>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import axios from 'axios';
    export default {
        props: {
                harvest:  { type:Object, default: () => {} },
               },
        data() {
            return {
                failure: '',
                success: '',
                new_status: '',
                mutable_harvest: this.harvest,
                status_canset: ['Restart', 'Stop'],
                status_fixed: ['Success', 'Active', 'Pending'],
            }
        },
        methods: {
            updateStatus() {
                let msg = "";
                if (this.new_status == 'Restart') {
                    msg += "Updating this harvest status will reset the attempts counter to zero and cause the";
                    msg += " system to add it immediately to the processing queue.";
                } else if (this.new_status == 'Stop') {
                    msg += "Changing this harvest's status will leave the attempts counter intact, and will prevent";
                    msg += " the system from running this harvest. Any future or queued attempts will be cancelled.";
                }
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: msg,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, Proceed!'
                }).then((result) => {
                  if (result.value) {
                    axios.post('/update-harvest-status', {
                        id: harvest.id,
                        status: self.new_status
                    })
                    .then( function(response) {
                        if (response.data.result) {
                            self.mutable_harvest = result.data.harvest;
                        } else {
                            self.failure = response.data.msg;
                        }
                    })
                    .catch(error => {});
                } else {
                    self.new_status = '';
                }
              })
              .catch({});
            },
            destroy (id) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting this record is not reversible, and no harvested data will be removed or changed. "+
                        "NOTE: all failure/warning records connected to this harvest will also be deleted.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/harvestlogs/'+id)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/harvestlogs");
                               } else {
                                   self.success = '';
                                   self.failure = response.data.msg;
                               }
                           })
                           .catch({});
                  }
                })
                .catch({});
            },
        },
        computed: {
            ...mapGetters(['is_manager', 'is_admin']),
        },
        mounted() {
            console.log('HarvestlogForm Component mounted.');
        }
    }
</script>
<style>
.align-mid { align-items: center; }
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
