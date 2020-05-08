<template>
  <v-container fluid grid-list-lg>
    <v-layout row wrap>
      <v-flex v-for="report in mutable_reports" :key="report.id">
        <v-card>
          <v-card-title>{{ report.title }}</v-card-title>
          <v-card-text class="headline font-weight-bold">
            <p>
              <span>
                <v-btn class='btn' small type="button" :href="'/savedreports/'+report.id+'edit'">Edit</v-btn>
              </span>
              <span>
                <v-btn class='btn btn-danger' small type="button" @click="destroy(report.id)">Delete</v-btn>
              </span>
            </p>
<!--
  Need a way to pass an inbound filter to the harvests index and specify it here...
-->
            <h5>Last Harvest: {{ report.last_harvest }}</h5>
            <h5>{{ report.successful }} / {{ report.inst_count }} institutions successful</h5>
            <p>
              <span>
                <v-btn class='btn' small type="button"
                       :href="'/harvestlogs?rept='+report.master_id+'&yrmo='+report.last_harvest">
                  Harvest details
                </v-btn>
              </span>
              <span>
                <v-btn class='btn' small type="button" :href="'/reports/preview?saved_id='+report.id">
                    Preview & Export
                </v-btn>
              </span>
            </p>
          </v-card-text>
        </v-card>
      </v-flex>
    </v-layout>
  </v-container>
</template>

<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  export default {
    props: {
            reports: { type:Array, default: () => [] },
           },
    data () {
      return {
          success: '',
          failure: '',
          mutable_reports: this.reports,
      }
    },
    methods: {
        destroy (id) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "Deleting this report cannot be reversed, only manually recreated.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, proceed'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/savedreports/'+id)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                               // Remove the setting from the display
                               this.mutable_reports.splice(this.mutable_reports.findIndex(s=> s.id == id),1);
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
    mounted() {
      console.log('HomeSavedReports Component mounted.');
    }
  }
</script>

<style>
</style>
