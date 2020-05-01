<template>
  <v-container fluid>
    <!-- <v-row no-gutters class="page-header"> -->
    <v-row no-gutters>
      <v-col><h3>{{ report.title }}</h3></v-col>
      <v-col v-if="is_admin">
         <v-btn class='btn btn-danger' small type="button" @click="destroy(report.id)">Delete</v-btn>
      </v-col>
    </v-row>

    <!-- <v-row class="details"> -->
    <v-row>
  	  <v-col><h4>Report Settings</h4></v-col>
      <v-col>
          <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
      </v-col>
    </v-row>
    <v-row v-if="!showForm">
      <v-col>
        <span class="form-good" role="alert" v-text="success"></span>
        <span class="form-fail" role="alert" v-text="failure"></span>
      </v-col>
    </v-row>
    <v-row v-if="!showForm">
      <v-col>
        <v-simple-table dense>
          <!-- form display control and confirmations  -->
          <!-- Values-only when form not active -->
          <tr v-if="is_admin">
            <td>Owner : {{ mutable_report.user.name }}</td>
          </tr>
          <tr>
            <td>Report covers {{ mutable_report.months }} month(s)</td>
          </tr>
          <tr v-if="typeof(filters['Institution Group']) != 'undefined'">
            <td>Includes all institutions in: {{ filters['Institution Group'].name }}</td>
          </tr>
          <tr><td>&nbsp;</td></tr>
          <tr>
            <td><h5>Includes Fields</h5></td>
          </tr>
          <template v-for="field in fields">
            <tr>
              <td>
                {{ field.legend }}
                <span v-if="typeof(filters[field.qry_as]) != 'undefined'">
                  <span v-if="filters[field.qry_as].name=='All'"> : <strong>All</strong></span>
                  <span v-else-if="filters[field.qry_as].name==''"></span>
                  <span v-else>equal to: <strong>{{ filters[field.qry_as].name }}</strong></span>
                </span>
              </td>
            </tr>
          </template>
        </v-simple-table>
      </v-col>
    </v-row>
    <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
    <v-row v-else>
      <v-col>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)"
              class="in-page-form">
            <tr v-if="is_admin">
              <td><h3>Owner : {{ mutable_report.user.name }}</h3></td>
            </tr>
            <tr>
              <td>
                <v-text-field v-model="form.title" label="Title" outlined value="mutable_report.title"></v-text-field>
              </td>
            </tr>
            <tr>
              <td>
                <v-text-field v-model="form.months" label="#-Months" outlined value="mutable_report.months">
                </v-text-field>
              </td>
            </tr>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save Report Settings
            </v-btn>
            <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
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
                report: { type:Object, default: () => {} },
                fields: { type:Object, default: () => {} },
                filters: { type:Object, default: () => {} },
               },

        data() {
            return {
                success: '',
                failure: '',
				showForm: false,
                mutable_report: this.report,
                form: new window.Form({
                    title: this.report.title,
                    months: this.report.months,
                })
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                this.form.patch('/savedreports/'+this.report['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_report.title = this.form.title;
                            this.mutable_report.months = this.form.months;
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
                                   window.location.assign("/savedreports");
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
          ...mapGetters(['is_manager', 'is_admin'])
        },
        mounted() {
            this.showForm = false;
            console.log('SavedReport Component mounted.');
        }
    }
</script>

<style>
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
