<template>
  <div>
    <v-row class="page-header">
      <v-col><h2>Group {{ group.name }}</h2></v-col>
      <v-col v-if="is_admin">
        <v-btn class='btn btn-danger' small type="button" @click="destroy(group.id)">Delete</v-btn>
      </v-col>
    </v-row>
    <div class="details">
  	  <h3 class="section-title">Details</h3>
      <template v-if="is_manager && !showForm">
    	<v-btn small color="primary" type="button" @click="swapForm" class="section-action">edit</v-btn>
		<div class="status-message" v-if="success || failure">
	        <span v-if="success" class="good" role="alert" v-text="success"></span>
	        <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
		</div>
   	  </template>
      <!-- form display control and confirmations  -->
      <!-- Values-only when form not active -->
      <div v-if="!showForm">
        <v-row>
          <v-col>Members</v-col>
        </v-row>
        <template v-for="inst in mutable_group.institutions">
          <v-row no-gutters>{{ inst.name }}</v-row>
        </template>
      </div>
      <!-- display form if manager has activated it. onSubmit function closes and resets showForm -->
      <div v-else>
        <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)" class="in-page-form">
          <v-row><v-col>
            <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
          </v-col></v-row>
          <v-row>
            <v-col>
              <v-subheader v-text="'Members'"></v-subheader>
              <v-select :items="mutable_not_members" v-model="curInst" return-object
                        item-text="name" item-value="id" label="Add Institution" @change="addInst"
                        hint="Add institution to the group" persistent-hint></v-select>
              <v-select :items="mutable_group.institutions" v-model="curInst" return-object
                        item-text="name" item-value="id" label="Remove Institution" @change="delInst"
                        hint="Remove institution from the group" persistent-hint></v-select>
            </v-col>
            <v-col>
              <template v-for="inst in mutable_group.institutions">
                <v-row no-gutters>{{ inst.name }}</v-row>
              </template>
            </v-col>
          </v-row>
          <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
            Save This Group
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
                group: { type:Object, default: () => {} },
                not_members: { type:Array, default: () => [] },
               },

        data() {
            return {
                success: '',
                failure: '',
				showForm: false,
                curInst: {},
                mutable_group: this.group,
                mutable_not_members: this.not_members,
                form: new window.Form({
                    name: this.group.name,
                    institutions: this.group.institutions,
                })
            }
        },
        methods: {
            addInst (inst) {
              // Add the entry to the members list and re-sort it
              this.mutable_group.institutions.push(inst);
              this.mutable_group.institutions.sort((a,b) => {
                  if ( a.name < b.name ) return -1;
                  if ( a.name > b.name ) return 1;
                  return 0;
              });
              // Remove the setting from the not_members list
              this.mutable_not_members.splice(this.mutable_not_members.findIndex(i=> i.id == inst.id),1);
              this.curInst = {};
			},
            delInst (inst) {
              // Add the entry to the not_members list and re-sort it
              this.mutable_not_members.push(inst);
              this.mutable_not_members.sort((a,b) => {
                  if ( a.name < b.name ) return -1;
                  if ( a.name > b.name ) return 1;
                  return 0;
              });
              // Remove the setting from the not_members list
              this.mutable_group.institutions.splice(this.mutable_group.institutions.findIndex(i=> i.id == inst.id),1);
              this.curInst = {};
			},
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                this.form.patch('/institutiongroups/'+this.group['id'])
                    .then( (response) => {
                        if (response.result) {
                            this.success = response.msg;
                            this.mutable_group.name = this.form.name;
                            this.mutable_group.institutions = this.form.institutions;
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
            destroy (groupid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting a group cannot be reversed, only manually recreated.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/institutiongroups/'+groupid)
                           .then( (response) => {
                               if (response.data.result) {
                                   window.location.assign("/institutiongroups");
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
            console.log('InstitutionGroup Component mounted.');
        }
    }
</script>

<style>

</style>
