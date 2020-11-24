<template>
  <v-container fluid>
    <v-row no-gutters>
      <v-col><h3>{{ report.title }}</h3></v-col>
      <v-col>
         <v-btn class='btn btn-danger' small type="button" @click="destroy(report.id)">Delete</v-btn>
      </v-col>
    </v-row>
    <v-row class="d-flex mb-0 pa-0">
  	  <v-col class="d-flex" cols="2" sm="2"><h4>Report Settings</h4></v-col>
      <v-col class="d-flex px-2" cols="1">
          <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
      </v-col>
      <v-col class="d-flex px-2" cols="1">
          <v-btn class='btn primary' small type="button" :href="'/reports/preview?saved_id='+report.id">
                  Preview & Export
          </v-btn>
      </v-col>
    </v-row>
    <v-row v-if="!showForm">
      <v-col class="status-message" v-if="success || failure">
        <span  v-if="success" class="form-good" role="alert" v-text="success"></span>
        <span  v-if="failure" class="form-fail" role="alert" v-text="failure"></span>
      </v-col>
    </v-row>
    <v-row v-if="!showForm" no-gutters>
      <!-- Values-only when form not active -->
      <v-col>
        <v-simple-table dense>
          <div v-if="is_admin">
            <tr><td>Owner : {{ mutable_report.user.name }}</td></tr>
            <tr><td>Report based on {{ mutable_report.report.name }} : {{ mutable_report.report.legend }}</td></tr>
          </div>
          <tr class="d-flex mb-5">
            <td v-if="mutable_report.date_range=='latestMonth'">
                Includes the latest month of available data
            </td>
            <td v-if="mutable_report.date_range=='latestYear'">
                Includes the latest year (up to 12 months) of available data
            </td>
            <td v-if="mutable_report.date_range=='Custom'">
                Includes data from {{ mutable_report.ym_from }} to {{ mutable_report.ym_to }}
            </td>
          </tr>
          <tr class="d-flex my-2"><td><h5>Includes Fields</h5></td></tr>
          <tr v-for="field in fields">
            <td>
              {{ field.legend }}
              <span v-if="typeof(filters[field.qry_as]) != 'undefined'">
                <span v-if="filters[field.qry_as].name == 'All'"> : <strong>All</strong></span>
                <span v-else-if="(filters[field.qry_as].name == '' || filters[field.qry_as].name == null)"></span>
                <span v-else>equal to: <strong>{{ filters[field.qry_as].name }}</strong></span>
              </span>
            </td>
          </tr>
          <tr class="d-flex my-2">
            <td>
              (Fields and filter settings can be changed on the
              <a :href="'/reports/preview?saved_id='+report.id">preview and export page</a>.)
            </td>
          </tr>
        </v-simple-table>
      </v-col>
    </v-row>
    <!-- display form if requested. onSubmit function closes and resets showForm -->
    <v-row v-else>
      <v-col>
        <v-container fluid>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
            <v-row v-if="is_admin">
              <v-col><h4>Owner : {{ mutable_report.user.name }}</h4></v-col>
            </v-row>
            <v-row>
              <v-col>
                <v-text-field v-model="form.title" label="Title" outlined value="mutable_report.title"></v-text-field>
              </v-col>
            </v-row>
            <v-row><v-col><h4>Report Dates</h4></v-col></v-row>
            <v-row>
              <v-col class="ma-2" cols="12">
                <v-radio-group v-model="form.date_range">
                  <v-radio :label="'Latest Month ['+maxYM+']'" value='latestMonth'></v-radio>
                  <v-radio :label="'Latest Year ['+latestYear+']'" value='latestYear'></v-radio>
                  <v-radio :label="'Custom Date Range'" value='Custom'></v-radio>
                </v-radio-group>
                <div v-if="form.date_range=='Custom'" class="d-flex pa-2">
                  <date-range :ymfrom="mutable_report.ym_from" :ymto="mutable_report.ym_to"
                              :minym="minYM" :maxym="maxYM"
                  ></date-range>
                </div>
              </v-col>
            </v-row>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save Report Settings
            </v-btn>
            <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
        </v-container>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                report:  { type:Object, default: () => {} },
                fields:  { type:Object, default: () => {} },
                filters: { type:Object, default: () => {} },
                bounds:  { type:Object, default: () => {} },
               },

        data() {
            return {
                success: '',
                failure: '',
				showForm: false,
                mutable_report: this.report,
                form: new window.Form({
                    title: this.report.title,
                    date_range: this.report.date_range,
                    ym_from: this.report.ym_from,
                    ym_to: this.report.ym_to,
                }),
                minYM: '',
                maxYM: '',
                latestYear: '',
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                // <date-range> component (used by Custom) puts selections in the store...
                if (this.form.date_range == 'Custom') {
                    this.form.ym_from = this.filter_by_fromYM;
                    this.form.ym_to = this.filter_by_toYM;
                // zap these if not custom
                } else {
                    this.form.ym_from = '';
                    this.form.ym_to = '';
                }
                this.form.patch('/savedreports/'+this.report['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.mutable_report.title = this.form.title;
                            this.mutable_report.date_range = this.form.date_range;
                            this.mutable_report.ym_from = this.form.ym_from;
                            this.mutable_report.ym_to = this.form.ym_to;
                            this.success = response.msg;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.showForm = false;
            },
            swapForm (event) {
                this.showForm = true;
			},
            hideForm (event) {
                this.showForm = false;
			},
            destroy (id) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting a saved report cannot be reversed, only manually recreated.",
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
                                   window.location.reload();
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
          ...mapGetters(['is_admin', 'filter_by_fromYM', 'filter_by_toYM'])
        },
        mounted() {
            this.showForm = false;
            this.minYM =  this.bounds['minYM'];
            this.maxYM = this.bounds['maxYM'];
            this.latestYear = this.bounds['latestYear'];
            // For filtering by Group, poke the institution legend
            if (this.filters['institution'].legend == 'Institution Group') {
                Object.keys(this.fields).forEach( (key) =>  {
                    if (this.fields[key].legend == 'Institution') {
                        this.fields[key].legend = this.filters['institution'].legend;
                    }
                });
            }
            console.log('SavedReport Component mounted.');
        }
    }
</script>

<style>

</style>
