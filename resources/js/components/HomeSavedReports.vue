<template>
  <v-container fluid grid-list-lg saved-report>
    <div v-if="mutable_reports.length>=1">
      <v-layout row wrap>
        <v-flex v-for="report in mutable_reports" :key="report.id">
          <v-card>
            <h3 class="v-card-title">{{ report.title }}</h3>
			<div class="v-card-actions">
	            <v-btn class='btn' small type="button" :href="'/savedreports/'+report.id+'/edit'">Edit</v-btn>
                <v-btn class='btn btn-danger' small type="button" @click="destroy(report.id)">Delete</v-btn>
			</div>
            <v-card-text class="headline font-weight-bold">
              <p>Last Harvest: {{ report.last_harvest }}</p>
              <div v-if="is_admin || is_viewer">
                <p>{{ report.successful }} / {{ report.inst_count }} institutions successful   <a href="'/harvestlogs?rept='+report.master_id+'&yrmo='+report.last_harvest">
                    Harvest details
                </a></p>
              </div>
              <div v-else>
                <p v-if="report.successful < report.inst_count">One or more harvests have failed   <a href="'/harvestlogs?rept='+report.master_id+'&yrmo='+report.last_harvest">
                    Harvest details
                </a></p>
                <p v-else>All harvests completed successfully   <a href="'/harvestlogs?rept='+report.master_id+'&yrmo='+report.last_harvest">
                    Harvest details
                </a></p>
              </div>
              <v-btn class='btn primary' small type="button" :href="'/reports/preview?saved_id='+report.id">
                      Preview & Export
              </v-btn>
            </v-card-text>
          </v-card>
        </v-flex>
      </v-layout>
    </div>
    <!-- done with saved reports... -->
  </v-container>
</template>

<script>
  import { mapGetters } from 'vuex'
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
    computed: {
      ...mapGetters(['is_admin','is_viewer'])
    },
    mounted() {
      console.log('HomeSavedReports Component mounted.');
    }
  }
</script>

<style>
</style>
