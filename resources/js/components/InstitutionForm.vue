<template>
  <div class="details">
	<h2 class="section-title">Details</h2>
  <div>
    <!-- form display control and confirmations  -->
    <div v-if="is_manager && !showForm">
  	  <v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
   	</div>
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
	          <v-chip v-if="mutable_groups.includes(group.id)">
	            {{ group.name }}
	          </v-chip>
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
</template>

<script>
    import { mapGetters } from 'vuex'
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
                mutable_inst: {},
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
                var self = this;
                this.form.patch('/institutions/'+self.institution['id'])
                    .then( function(response) {
                        if (response.result) {
                            self.success = response.msg;
                            self.mutable_inst.name = self.form.name;
                            self.mutable_inst.type_id = self.form.type_id;
                            self.inst_type = self.types[self.form.type_id].name;
                            self.mutable_inst.is_active = self.form.is_active;
                            self.status = self.statusvals[self.form.is_active];
                            self.mutable_inst.fte = self.form.fte;
                            self.mutable_inst.notes = self.form.notes;
                            self.mutable_groups = self.form.institutiongroups;
                        } else {
                            self.failure = response.msg;
                        }
                    });
                self.showForm = false;
            },
            swapForm (event) {
                var self = this;
                self.showForm = true;
			},
            hideForm (event) {
                var self = this;
                self.showForm = false;
			},
        },
        computed: {
          ...mapGetters(['is_manager'])
        },
        mounted() {
            this.showForm = false;
            this.status=this.statusvals[this.institution.is_active];
            this.inst_type = this.types[this.institution.type_id-1].name;
            Object.assign(this.mutable_inst, this.institution);
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
