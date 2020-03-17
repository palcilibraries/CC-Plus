<template>
  <div class="form-field">
    <v-select
          :items="this.sectiontype_options"
          v-model="type_id"
          @change="onChange"
          label="Section Type"
          placeholder="Filter by Section Type"
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
        sectiontypes: { type:Array, default: () => [] },
    },
    data() {
      return {
        type_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (type) {
        var self = this;
        this.$store.dispatch('updateSectionTypeFilter', type);
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
            self.$store.dispatch('updateInstitutionOptions',response.data.filters.institutions);
            self.$store.dispatch('updatePlatformOptions',response.data.filters.platforms);
            self.$store.dispatch('updateProviderOptions',response.data.filters.providers);
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id','sectiontype_options'])
    },
    mounted() {
      if (!this.sectiontype_options.length) {
          this.$store.dispatch('updateSectionTypeOptions',this.sectiontypes);
      }
    }
  }
</script>

<style>
</style>
