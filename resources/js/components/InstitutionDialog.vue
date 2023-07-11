<template>
  <div>
    <v-form v-model="formValid">
      <v-row class="d-flex ma-2" no-gutters>
        <v-col v-if="dtype=='edit'" class="d-flex pt-4 justify-center"><h1 align="center">Edit Institution settings</h1></v-col>
        <v-col v-else class="d-flex pt-4 justify-center"><h1 align="center">Create an Institution</h1></v-col>
      </v-row>
      <v-row class="d-flex mx-2" no-gutters>
        <v-text-field v-model="form.name" label="Name" outlined dense :readonly="!is_admin"></v-text-field>
      </v-row>
      <v-row class="d-flex mx-2" no-gutters>
        <v-col class="d-flex justify-center" cols="8">
          <v-text-field v-model="form.local_id" label="Internal Identifier" outlined dense :readonly="!is_admin"></v-text-field>
        </v-col>
        <v-col class="d-flex justify-center" cols="4">
          <v-switch v-model="form.is_active" label="Active?" dense :readonly="!is_admin"></v-switch>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2" no-gutters>
        <v-col class="d-flex px-2" cols="1">FTE</v-col>
        <v-col class="d-flex px-2" cols="2">
          <v-text-field v-model="form.fte" label="FTE" hide-details single-line type="number" dense></v-text-field>
        </v-col>
      </v-row>
      <v-row class="d-flex mx-2 mt-2" no-gutters>
        <div class="field-wrapper has-label">
          <v-subheader v-text="'Belongs To'"></v-subheader>
          <v-select :items="groups" v-model="form.institution_groups" item-text="name" item-value="id"
                    label="Institution Group(s)" multiple chips persistent-hint  :readonly="!is_admin"
                    hint="Assign group membership for this institution"
          ></v-select>
        </div>
      </v-row>
      <v-row class="d-flex mx-2" no-gutters>
        <v-col class="d-flex px-2">
          <v-textarea v-model="form.notes" label="Notes" rows="2" auto-grow></v-textarea>
        </v-col>
      </v-row>
      <v-row class="d-flex ma-2" no-gutters>
        <v-spacer></v-spacer>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn class='btn' x-small color="primary" @click="saveInst" :disabled="!formValid">Save Institution</v-btn>
        </v-col>
        <v-col class="d-flex px-2 justify-center" cols="6">
          <v-btn class='btn' x-small type="button" color="primary" @click="cancelDialog">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-form>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  import axios from 'axios';
  export default {
    props: {
            dtype: { type: String, default: "create" },
            institution: { type:Object, default: () => {} },
            groups: { type:Array, default: () => [] },
           },
    data () {
      return {
        formValid: true,
        emailRules: [
            v => !!v || 'E-mail is required',
            v => ( /.+@.+/.test(v) || v=='Administrator') || 'E-mail must be valid'
        ],
        form: new window.Form({
            name: '',
            local_id: '',
            is_active: 1,
            fte: 0,
            institution_groups: [],
            notes: '',
        }),
      }
    },
    methods: {
      saveInst (event) {
          if (this.dtype == 'edit') {
            this.form.patch('/institutions/'+this.institution['id'])
                .then( (response) => {
                    var _inst   = (response.result) ? response.institution : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.$emit('inst-complete', { result:_result, msg:response.msg, inst:_inst });
            });
          } else {
            this.form.post('/institutions')
                .then( (response) => {
                    var _inst   = (response.result) ? response.institution : null;
                    var _result = (response.result) ? 'Success' : 'Fail';
                    this.$emit('inst-complete', { result:_result, msg:response.msg, inst:_inst });
                });
          }
      },
      cancelDialog () {
        this.$emit('inst-complete', { result:'Cancel', msg:null, inst:null });
      },
    },
    computed: {
      ...mapGetters(['is_admin']),
    },
    mounted() {
      if (this.dtype == 'edit') {
        this.form.name = this.institution.name;
        this.form.local_id = this.institution.local_id;
        this.form.is_active = this.institution.is_active;
        this.form.fte = this.institution.fte;
        this.form.institution_groups = this.institution.groups;
        this.form.notes = this.institution.notes;
      } else if (this.dtype == 'create') {
        this.form.name = '';
        this.form.local_id = '';
        this.form.is_active = 1;
        this.form.fte = 0;
        this.form.institution_groups = [];
        this.form.notes = '';
      }
      console.log('InstitutionDialog Component mounted.');
    }
  }
</script>
<style>
</style>
