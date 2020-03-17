<template>
  <div class="form-field">
    <v-select
          :items="this.institution_options"
          v-model="inst_id"
          @change="onChange"
          label="Institution"
          placeholder="Filter by Institution"
          item-text="name"
          item-value="id"
    ></v-select>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex'
  import axios from 'axios';
  export default {
    props: {
        institutions: { type:Array, default: () => [] },
    },
    data() {
      return {
        inst_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (inst) {
        var self = this;
        this.$store.dispatch('updateInstitutionFilter', inst);
// -- Hard-wired for testing
this.$store.dispatch('updateMasterId', 1);
// --
        axios.post('/update-report-filters', {
            filters: self.all_filters,
            master_id: self.master_id,
        })
        .then( function(response) {
            self.$store.dispatch('updateAccessMethodOptions',response.data.filters.accessmethods);
            self.$store.dispatch('updateAccessTypeOptions',response.data.filters.accesstypes);
            self.$store.dispatch('updateDataTypeOptions',response.data.filters.datatypes);
            self.$store.dispatch('updatePlatformOptions',response.data.filters.platforms);
            self.$store.dispatch('updateProviderOptions',response.data.filters.providers);
            self.$store.dispatch('updateSectionTypeOptions',response.data.filters.sectiontypes);
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id','institution_options'])
    },
    mounted() {
      if (!this.institution_options.length) {
          this.$store.dispatch('updateInstitutionOptions',this.institutions);
      }
    }
  }
</script>

<style>
</style>
