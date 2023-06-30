<template>
  <div>
    <v-row class="d-flex align-center" no-gutters>
      <v-col><h1 class="d-flex section-title">Harvest Details</h1></v-col>
      <v-col class="d-flex justify-center harvest-status">Status: {{ mutable_harvest.status }}</v-col>
      <v-col class="d-flex">
        <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_harvest.id)">Delete</v-btn>
      </v-col>
    </v-row>
    <v-row class="d-flex align-center" no-gutters>
      <v-col v-if="mutable_harvest.status=='Success'" class="d-flex justify-center harvest-status">
        Last Attempt Succeeded
      </v-col>
      <v-col v-else class="d-flex justify-center harvest-status">
        Latest Result: {{ last_attempt.message }} &nbsp;
        <div v-if="last_attempt.severity!='Unknown'" class="x-box">
          <img src="/images/blue-qm-16.png" width="100%" alt="clear filter" @click="errorDetail"/>&nbsp;
        </div>
      </v-col>
    </v-row>
    <div v-if="(is_manager || is_admin)" class="d-flex ma-2 pa-0">
      <!-- Some statuses cannot be changed -->
      <v-row v-if="!(status_fixed.includes(mutable_harvest.status))" no-gutters class="d-flex align-center">
        <v-col class="d-flex justify-center harvest-status">
          <v-btn v-if="mutable_harvest.status!='Stopped'" color="primary" x-small @click="newStatus('Stopped')">
            Stop
          </v-btn>
          &nbsp; &nbsp; &nbsp;
          <v-btn v-if="mutable_harvest.status!='Queued'" color="primary" x-small @click="newStatus('Queued')">
            Restart
          </v-btn>
        </v-col>
      </v-row>
    </div>
    <v-row class="status-message" v-if="success || failure">
      <span v-if="success" class="good" role="alert" v-text="success"></span>
      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex px-4 justify-end" cols="2">Institution:</v-col>
      <v-col class="d-flex px-4 justify-start" cols="4">
        <a :href="'/institutions/'+mutable_harvest.sushi_setting.institution.id">
            {{ mutable_harvest.sushi_setting.institution.name }}
        </a>
      </v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex pr-4 justify-end" cols="2">Provider:</v-col>
      <v-col class="d-flex pl-4 justify-start" cols="4">
        <a :href="'/providers/'+mutable_harvest.sushi_setting.provider.id">
          {{ mutable_harvest.sushi_setting.provider.name }}
        </a>
      </v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex pr-4 justify-end" cols="2">Report:</v-col>
      <v-col class="d-flex pl-4 justify-start" cols="4">{{ mutable_harvest.report.name }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex pr-4 justify-end" cols="2">Usage Month:</v-col>
      <v-col class="d-flex pl-4 justify-start" cols="4">{{ mutable_harvest.yearmon }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex pr-4 justify-end" cols="2">Attempts:</v-col>
      <v-col class="d-flex pl-4 justify-start" cols="2">{{ mutable_harvest.attempts }}</v-col>
    </v-row>
    <v-row no-gutters>
      <v-col class="d-flex pr-4 justify-end" cols="2">Raw Data</v-col>
      <v-col v-if="mutable_harvest.rawfile" class="d-flex pl-4 justify-start" cols="4">
        <a :href="'/harvests/'+mutable_harvest.id+'/raw'"><v-btn color="primary" x-small>download</v-btn></a>
      </v-col>
      <v-col v-else class="d-flex pl-4 justify-start" cols="4">
        <strong><font color="red">Not available</font></strong></v-col>
      </v-col>
    </v-row>
    <v-dialog v-model="errorDialog" max-width="500px">
      <v-card>
        <v-card-title>{{ last_attempt.message }}</v-card-title>
        </v-card-subtitle>
        <v-card-text>
          <v-container grid-list-md>
            <h5>Explanation</h5>
            <p>{{ explain_text }}</p>
            <h5>Suggested next step(s)</h5>
            <p>{{ suggest_text }}</p>
          </v-container>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
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
                mutable_harvest: this.harvest,
                status_fixed: ['Success', 'Active', 'Pending'],
                errorDialog: false,
                explain_text: '',
                suggest_text: '',
            }
        },
        methods: {
            newStatus(stat) {
                let msg = "";
                if (stat == 'Queued') {
                    msg += "Updating this harvest status will reset the attempts counter to zero and cause the";
                    msg += " system to add it immediately to the processing queue.";
                } else if (stat == 'Stopped') {
                    msg += "Changing this harvest's status will leave the attempts counter intact, and will prevent";
                    msg += " the system from running this harvest. Any future or queued attempts will be cancelled.";
                }
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
                    this.postNewStatus(stat);
                  }
              })
              .catch({});
            },
            postNewStatus: async function (stat) {
                var updated_harvest = '';
                await axios.post('/update-harvest-status', {
                    id: this.mutable_harvest.id,
                    status: stat
                })
                .then((response) => {
                    if (response.data.result) {
                        updated_harvest = response.data.harvest;
                    } else {
                        this.failure = response.data.msg;
                        return;
                    }
                })
                .catch(error => {});
                Object.assign(this.mutable_harvest, updated_harvest);
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
                      axios.delete('/harvests/'+id)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/harvests");
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
            errorDetail() {
                if (this.last_attempt.ccplus_error) {
                    this.explain_text = this.last_attempt.ccplus_error.explanation;
                    this.suggest_text = this.last_attempt.ccplus_error.suggestion;
                    this.errorDialog = true;
                }
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
.x-box { width: 16px;  height: 16px; flex-shrink: 0; }
</style>
