<template>
  <div>
    <div class="page-header"><h1>{{ form.name }}</h1></div>
    <div class="details">
  	  <v-row v-if="(is_admin || is_manager) && showForm==''" class="d-flex ma-0" no-gutters>
        <v-col class="d-flex pa-0"><h2 class="section-title">Details</h2></v-col>
        <v-col class="d-flex px-2">
          <v-btn small color="primary" type="button" @click="editForm" class="section-action">edit</v-btn>
        </v-col>
        <v-col v-if="is_admin && mutable_inst.can_delete" class="d-flex px-2">
          <v-btn class='btn btn-danger' small type="button" @click="destroy(mutable_inst.id)">Delete</v-btn>
        </v-col>
      </v-row>
	    <div class="status-message" v-if="success || failure">
	      <span v-if="success" class="good" role="alert" v-text="success"></span>
	      <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
	    </div>
      <!-- Values-only when form not active -->
      <div v-if="showForm==''">
	      <v-simple-table dense>
	        <tr>
	          <td>Name </td>
	          <td>{{ mutable_inst.name }}</td>
	        </tr>
          <tr>
	          <td>Internal ID </td>
	          <td>{{ mutable_inst.internal_id }}</td>
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
	        <tr v-if="mutable_inst.notes">
	          <td>Notes </td>
	          <td>{{ mutable_inst.notes }}</td>
	        </tr>
	      </v-simple-table>
      </div>
      <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
      <div v-if="showForm=='edit'">
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
          <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          <v-text-field v-model="form.internal_id" label="Internal Identifier" outlined></v-text-field>
          <v-switch v-model="form.is_active" label="Active?"></v-switch>
			      <div class="field-wrapper">
	            <v-subheader v-text="'FTE'"></v-subheader>
	            <v-text-field v-model="form.fte" label="FTE" hide-details single-line type="number"
	            ></v-text-field>
			      </div>
            <div class="field-wrapper has-label">
	            <v-subheader v-text="'Belongs To'"></v-subheader>
	            <v-select :items="all_groups" v-model="form.institutiongroups" value="mutable_groups"
	                      item-text="name" item-value="id" label="Institution Group(s)" multiple
	                      chips hint="Assign group membership for this institution" persistent-hint
	            ></v-select>
            </div>
            <v-textarea v-model="form.notes" value="mutable_inst.notes" label="Notes" auto-grow
            ></v-textarea>
            <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
              Save Institution Settings
            </v-btn>
            <v-btn small type="button" @click="hideForm">cancel</v-btn>
        </form>
      </div>
      <div v-if="showForm=='import'">
        <v-file-input show-size label="CC+ Import File" v-model="csv_upload" accept="text/csv" outlined></v-file-input>
        <p>
          <strong>Note:&nbsp; The Import Type below determines whether the settings in the input file should
          be treated as an <em>Update</em> or as a <em>Full Replacement</em> for any existing settings.</strong>
        </p>
        <p>
          When "Full Replacement" is chosen, any EXISTING SETTINGS omitted from the import file will be deleted!
          This will also remove all associated harvest and failed-harvest records connected to the settings!
        </p>
        <p>
          The "Add or Update" option will not delete any sushi settings, but will overwrite existing settings
          whenever a match for an Institution-ID and Provider-ID are found in the import file. If no setting
          exists for a given valid provider-institution pair, a new setting will be created and saved. Any values
          in columns C-G which are NULL, blank, or missing for a valid provider-institution pair, will result
          in a NULL value being stored for that field.
        </p>
        <p>
          For these reasons, exercise caution using this import function, especially when requesting a Full
          Replacement import. Generating an export of the existing settings FIRST will provide detailed
          instructions for importing on the "How to Import" tab and will help ensure that the desired
          end-state is achieved.
        </p>
        <v-select :items="import_types" v-model="import_type" label="Import Type" outlined></v-select>
        <v-btn small color="primary" type="submit" @click="importSubmit">Run Import</v-btn>
        <v-btn small type="button" @click="hideForm">cancel</v-btn>
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
                all_groups: { type:Array, default: () => [] },
               },

        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
				        showForm: '',
                mutable_inst: this.institution,
                mutable_groups: this.institution.groups,
                form: new window.Form({
                    name: this.institution.name,
                    internal_id: this.institution.internal_id,
                    is_active: this.institution.is_active,
                    fte: this.institution.fte,
                    institutiongroups: this.institution.groups,
                    notes: this.institution.notes,
                }),
                csv_upload: null,
                import_type: '',
                import_types: ['Add or Update', 'Full Replacement']
            }
        },
        methods: {
            importForm () {
                this.csv_upload = null;
                this.import_type = '';
                this.showForm = 'import';
            },
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                this.form.patch('/institutions/'+this.institution['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_inst.name = this.form.name;
                            this.mutable_inst.internal_id = this.form.internal_id;
                            this.mutable_inst.is_active = this.form.is_active;
                            this.status = this.statusvals[this.form.is_active];
                            this.mutable_inst.fte = this.form.fte;
                            this.mutable_inst.notes = this.form.notes;
                            this.mutable_groups = this.form.institutiongroups;
                        } else {
                            this.failure = response.msg;
                        }
                    });
                this.showForm = '';
            },
            importSubmit (event) {
                this.success = '';
                if (this.import_type == '') {
                    this.failure = 'An import type is required';
                    return;
                }
                if (this.csv_upload==null) {
                    this.failure = 'A CSV import file is required';
                    return;
                }
                this.failure = '';
                let formData = new FormData();
                formData.append('csvfile', this.csv_upload);
                formData.append('type', this.import_type);
                axios.post('/sushisettings/import', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                      })
                     .then( (response) => {
                         if (response.data.result) {
                             this.success = response.data.msg;
                         } else {
                             this.failure = response.data.msg;
                         }
                     });
                this.showForm = '';
            },
            editForm (event) {
                this.showForm = 'edit';
			      },
            hideForm (event) {
                this.showForm = '';
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
            this.showForm = '';
            this.status=this.statusvals[this.institution.is_active];
            console.log('Institution Component mounted.');
        }
    }
</script>
