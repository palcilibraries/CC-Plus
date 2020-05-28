<template>
  <div class="details">
  	  <h2 class="section-title">Details</h2>
      <div v-if="is_manager && !showForm">
        <v-row>
          <v-col>
            <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
          </v-col>
          <v-col v-if="is_admin && mutable_inst.can_delete">
            <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_inst.id)">Delete</v-btn>
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
	          <td>{{ mutable_inst.name }}</td>
	        </tr>
	        <tr>
	          <td>Type </td>
	          <td>{{ inst_type }}</td>
	        </tr>
	        <tr>
    	      <td>Status </td>
	          <td>{{ status }}</td>
	        </tr>
	        <tr>
	          <td>FTE </td>
	          <td>{{ mutable_inst.fte }}</td>
	        </tr>
	        <tr>
	          <td>Groups </td>
	          <td>
	            <template v-for="group in all_groups">
	              <v-chip v-if="mutable_groups.includes(group.id)">{{ group.name }}</v-chip>
    	        </template>
	          </td>
	        </tr>
	        <tr>
	          <td>Notes </td>
	          <td>{{ mutable_inst.notes }}</td>
	        </tr>
	      </v-simple-table>
        </div>
        <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
        <div v-else>
          <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
              <v-select
                  :items="types"
                  v-model="form.type_id"
                  value="mutable_inst.type_id"
                  label="Institution Type"
                  item-text="name"
                  item-value="id"
                  outlined
              ></v-select>
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
			  <div class="field-wrapper">
	              <v-subheader v-text="'FTE'"></v-subheader>
	              <v-text-field v-model="form.fte"
	                            label="FTE"
	                            hide-details
	                            single-line
	                            type="number"
	              ></v-text-field>
			  </div>
			  <div class="field-wrapper has-label">
	              <v-subheader v-text="'Belongs To'"></v-subheader>
	              <v-select
	                  :items="all_groups"
	                  v-model="form.institutiongroups"
	                  value="mutable_groups"
	                  item-text="name"
	                  item-value="id"
	                  label="Institution Group(s)"
	                  multiple
	                  chips
	                  hint="Assign group membership for this institution"
	                  persistent-hint
	              ></v-select>
			  </div>
              <v-textarea
                  v-model="form.notes"
                  value="mutable_inst.notes"
                  label="Notes"
                  auto-grow
              ></v-textarea>
              <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
                Save Institution Settings
              </v-btn>
			  <v-btn small type="button" @click="hideForm">cancel</v-btn>
          </form>
        </div>
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
                institution: { type:Object, default: () => {} },
                types: { type:Array, default: () => [] },
                inst_groups: { type:Array, default: () => [] },
                all_groups: { type:Array, default: () => [] },
               },

        data() {
            return {
                success: '',
                failure: '',
                status: '',
                inst_type: '',
                statusvals: ['Inactive','Active'],
				showForm: false,
                mutable_inst: this.institution,
                mutable_groups: this.inst_groups,
                form: new window.Form({
                    name: this.institution.name,
                    is_active: this.institution.is_active,
                    fte: this.institution.fte,
                    type_id: this.institution.type_id,
                    institutiongroups: this.inst_groups,
                    notes: this.institution.notes,
                })
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                this.form.patch('/institutions/'+this.institution['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_inst.name = this.form.name;
                            this.mutable_inst.type_id = this.form.type_id;
                            this.inst_type = this.types[this.form.type_id].name;
                            this.mutable_inst.is_active = this.form.is_active;
                            this.status = this.statusvals[this.form.is_active];
                            this.mutable_inst.fte = this.form.fte;
                            this.mutable_inst.notes = this.form.notes;
                            this.mutable_groups = this.form.institutiongroups;
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
            destroy (instid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting an institution cannot be reversed, only manually recreated."+
                        " Because this institution has no harvested usage data, it can be safely"+
                        " deleted. NOTE: All users and SUSHI settings connected to this institution"+
                        " will also be removed.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/institutions/'+instid)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/institutions");
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
            this.status=this.statusvals[this.institution.is_active];
            this.inst_type = this.types[this.institution.type_id-1].name;
            console.log('Institution Component mounted.');
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
