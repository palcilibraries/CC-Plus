<template>
  <v-container fluid>
    <v-row no-gutters>
      <v-col><h3 class="section-title">Harvest Details</h3></v-col>
<!--
      <v-col v-if="mutable_harvest.status=='Success'" class="harvest-status">Last Attempt Succeeded</v-col>
      <v-col v-else class="harvest-status">
          Latest Result: {{ last_attempt.message }}
      </v-col>
  -->
      <v-col cols="1" sm="1">
        <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_harvest.id)">Delete</v-btn>
      </v-col>
    </v-row>
    <v-row class="d-flex align-center" no-gutters>
      <v-col v-if="mutable_harvest.status=='Success'" class="d-flex justify-center harvest-status">
        Last Attempt Succeeded
      </v-col>
      <v-col v-else class="d-flex justify-center harvest-status">
        Latest Result: {{ last_attempt.message }} &nbsp;
        <div class="x-box">
          <img src="/images/blue-qm-16.png" width="100%" alt="clear filter" @click="errorDialog=true;"/>&nbsp;
        </div>
<!--
This would be a good place to link in a small overlay connected to a (?) ... for ... what does this mean?
And then display the last_attempt.ccplus_error.explanation and last_attempt.ccplus_error.suggestion info
in the pop-up.   ??? Could also connect it to a mouseover action in the Attempts component table rows...???
-->
      </v-col>
    </v-row>
    <v-row class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Institution</v-col>
      <v-col cols="4" sm="2">
        <a :href="'/institutions/'+mutable_harvest.sushi_setting.institution.id">
            {{ mutable_harvest.sushi_setting.institution.name }}
        </a>
      </v-col>
    </v-row>
    <v-row no-gutters>
      <v-col cols="2" sm="1">Provider</v-col>
      <v-col cols="4" sm="2">
        <a :href="'/providers/'+mutable_harvest.sushi_setting.provider.id">
          {{ mutable_harvest.sushi_setting.provider.name }}
        </a>
      </v-col>
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
    <v-row v-if="mutable_harvest.rawfile" no-gutters class="d-flex align-mid">
      <v-col cols="2" sm="1">Raw Data</v-col>
      <v-col cols="2" sm="1">
        <a :href="'/harvestlogs/'+mutable_harvest.id+'/raw'"><v-btn color="primary" x-small>download</v-btn></a>
      </v-col>
    </v-row>
    <v-row v-else class="d-flex my-2 align-mid">
      <v-col cols="8" sm="4"><strong>Raw Data is not available</strong></v-col>
    </v-row>
<!--
    <div class="harvest-status">
      Status: &nbsp;&nbsp; {{ mutable_harvest.status }}
    </div>
-->
    <div v-if="(is_manager || is_admin)" class="d-flex ma-2 pa-0">
      <!-- Some statuses cannot be changed -->
      <v-row v-if="!(status_fixed.includes(mutable_harvest.status))" no-gutters class="d-flex align-mid">
        <v-col cols="2" sm="2">
          <v-select :items="status_canset" v-model="new_status" label="Modify Status" dense @change="updateStatus()">
          </v-select>
        </v-col>
      </v-row>
    </div>
    <v-dialog v-model="errorDialog" max-width="500px">
      <v-card>
        <v-card-title>{{ last_attempt.message }}</v-card-title>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <h5>Explanation</h5>
            <p>{{ last_attempt.ccplus_error.explanation }}</p>
            <h5>Suggested next step(s)</h5>
            <p>{{ last_attempt.ccplus_error.suggestion }}</p>
          </v-container>
        </v-card-text>
<!--
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-col class="d-flex">
            <v-btn x-small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
          </v-col>
          <v-col class="d-flex">
            <v-btn class='btn' x-small type="button" color="primary" @click="importDialog=false">Cancel</v-btn>
          </v-col>
        </v-card-actions>
-->
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import axios from 'axios';
    export default {
        props: {
                harvest:  { type:Object, default: () => {} },
                last_attempt: { type:Object, default: () => {} },
               },
        data() {
            return {
                failure: '',
                success: '',
                new_status: '',
                mutable_harvest: this.harvest,
                status_canset: ['Restart', 'Stop'],
                status_fixed: ['Success', 'Active', 'Pending'],
                errorDialog: false,
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
                        id: self.mutable_harvest.id,
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
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
