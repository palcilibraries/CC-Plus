<template>
  <div>
    <div class="page-header"><h1>{{ form.name }}</h1></div>
    <div class="details">
      <v-row v-if="can_edit && !showForm" no-gutters>
        <v-col class="d-flex ma-2" cols="2" sm="2">
          <h2 class="section-title">Details</h2>
        </v-col>
        <v-col class="d-flex ma-2" cols="2" sm="2">
          <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
        </v-col>
        <v-col v-if="is_admin && mutable_prov.can_delete" class="d-flex ma-2" cols="2" sm="2">
          <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_prov.id)">Delete</v-btn>
        </v-col>
  	    <div class="status-message" v-if="success || failure">
  	      <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
	    </div>
      </v-row>
      <!-- form display control and confirmations  -->
      <!-- Values-only when form not active -->
      <div v-if="!showForm">
  	    <v-simple-table dense>
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
  	        <td>Maximum retries </td>
  	        <td>{{ mutable_prov.max_retries }}</td>
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
        <v-form v-model="formValid" class="in-page-form">
          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          <v-switch v-model="form.is_active" label="Active?"></v-switch>
          <v-select :items="institutions" v-model="form.inst_id" value="provider.inst_id" label="Serves"
                    item-text="name" item-value="id" outlined
          ></v-select>
          <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
  		  <div class="field-wrapper has-label">
  	        <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
  	        <v-text-field v-model="form.day_of_month" label="Day-of-Month" single-line type="number"
	                      :rules="dayRules"></v-text-field>
		  </div>
          <div class="field-wrapper has-label">
            <v-subheader v-text="'Maximum #-of Retries'"></v-subheader>
            <v-text-field v-model="form.max_retries" label="Max Retries" hide-details single-line type="number"
            ></v-text-field>
          </div>
	      <div class="field-wrapper has-label">
	        <v-subheader v-text="'Reports to Harvest'"></v-subheader>
	        <v-select :items="master_reports" v-model="form.master_reports" value="provider.reports" label="Select"
	                  item-text="name" item-value="id" multiple chips hint="Choose which reports to harvest"
                      persistent-hint
	        ></v-select>
		  </div>
          <br />
          <v-btn small color="primary" type="button" @click="formSubmit" :disabled="!formValid">
              Save Provider Settings
          </v-btn>
          &nbsp; &nbsp;
          <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </v-form>
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
                    max_retries: this.provider.max_retries,
                    master_reports: [],
                }),
                formValid: true,
                dayRules: [
                    v => !!v || "Day of month is required",
                    v => ( v && v >= 1 ) || "Day of month must be > 1",
                    v => ( v && v <= 28 ) || "Day of month must be < 29",
                ],
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
                            self.form.max_retries = response.provider.max_retries;
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
            // Setup form:master_reports
            for(var i=0;i<this.provider.reports.length;i++){
               this.form.master_reports.push(this.provider.reports[i].id);
            }
            console.log('Provider Component mounted.');
        }
    }
</script>

<style>

</style>
