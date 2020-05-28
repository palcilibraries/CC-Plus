<template>
  <div class="details">
    <h2 class="section-title">Details</h2>
    <div v-if="can_edit && !showForm">
      <v-row>
        <v-col>
          <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
        </v-col>
        <v-col v-if="is_admin && mutable_prov.can_delete">
          <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_prov.id)">Delete</v-btn>
        </v-col>
      </v-row>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </div>
	<div>
    <!-- form display control and confirmations  -->
    <!-- Values-only when form not active -->
    <div v-if="!showForm">
	  <v-simple-table>
  	    <tr>
  	      <td>Name </td>
  	      <td>{{ mutable_prov.name }}</td>
  	    </tr>
  	    <tr>
  	      <td>Status </td>
  	      <td>{{ status }}</td>
  	    </tr>
  	    <tr>
  	      <td>Serves </td>
  	      <td>{{ inst_name }}</td>
  	    </tr>
  	    <tr>
  	      <td>SUSHI service URL </td>
  	      <td>{{ mutable_prov.server_url_r5 }}</td>
  	    </tr>
  	    <tr>
  	      <td>Run harvests monthly on day </td>
  	      <td>{{ mutable_prov.day_of_month }}</td>
  	    </tr>
  	    <tr>
  	      <td>Reports to harvest </td>
  	      <td>
  	        <template v-for="report in master_reports">
              <v-chip v-if="mutable_prov.reports.some(r => r.id === report.id)">
  	            {{ report.name }}
  	          </v-chip>
  	        </template>
  	      </td>
  	    </tr>
  	  </v-simple-table>
    </div>

    <div v-else>
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)">
	          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
              <v-select
                  :items="institutions"
                  v-model="form.inst_id"
                  value="provider.inst_id"
                  label="Serves"
                  item-text="name"
                  item-value="id"
                  outlined
              ></v-select>
              <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
			  <div class="field-wrapper has-label">
	              <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
	              <v-text-field v-model="form.day_of_month"
	                            label="Day-of-Month"
	                            hide-details
	                            single-line
	                            type="number"
	              ></v-text-field>
			  </div>
			  <div class="field-wrapper has-label">
	              <v-subheader v-text="'Reports to Harvest'"></v-subheader>
	              <v-select
	                  :items="master_reports"
	                  v-model="form.master_reports"
	                  value="provider.reports"
	                  item-text="name"
	                  item-value="id"
	                  label="Select"
	                  multiple
	                  chips
	                  hint="Choose which reports to harvest"
	                  persistent-hint
	              ></v-select>
			  </div>
              <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
                  Save Provider Settings
              </v-btn>
			  <v-btn small type="button" @click="hideForm">cancel</v-btn>
      </form>
    </div>
    </div>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                provider: { type:Object, default: () => {} },
                institutions: { type:Array, default: () => [] },
                master_reports: { type:Array, default: () => [] },
               },

        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
                inst_name: '',
                can_edit: false,
				showForm: false,
                mutable_prov: this.provider,
                form: new window.Form({
                    name: this.provider.name,
                    inst_id: this.provider.inst_id,
                    is_active: this.provider.is_active,
                    server_url_r5: this.provider.server_url_r5,
                    day_of_month: this.provider.day_of_month,
                    master_reports: this.provider.reports,
                })
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                var self = this;
                this.form.patch('/providers/'+self.provider['id'])
                    .then( function(response) {
                        if (response.result) {
                            self.mutable_prov = response.provider;
                            if (self.is_admin) {
                                self.inst_name = self.institutions[response.provider.inst_id-1].name;
                            }
							self.status = self.statusvals[response.provider.is_active];
                            self.success = response.msg;
                        } else {
                            self.failure = response.msg;
                        }
                    });
				self.showForm = false;
            },
            swapForm (event) {
                this.showForm = true;
			},
            hideForm (event) {
                this.showForm = false;
			},
            destroy (provid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting a provider cannot be reversed, only manually recreated."+
                        " Because this provider has no harvested usage data, it can be safely"+
                        " deleted. NOTE: All SUSHI settings connected to this provider"+
                        " will also be removed.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/providers/'+provid)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/providers");
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
          ...mapGetters(['is_manager','is_admin','user_inst_id'])
        },
        mounted() {
			this.showForm = false;
            if ( this.provider.inst_id==1 ) {
                this.inst_name="Entire Consortium";
            } else {
                if (this.is_admin) {
                    this.inst_name = this.institutions[this.provider.inst_id-1].name;
                } else {
                    this.inst_name = this.institutions[0].name;
                }
            }
            if ( this.is_admin || (this.is_manager && this.provider.inst_id==this.user_inst_id)) {
                this.can_edit = true;
            }
            this.status=this.statusvals[this.provider.is_active];
            console.log('Provider Component mounted.');
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
