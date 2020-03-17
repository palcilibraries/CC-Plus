<template>
  <div class="form-field">
    <v-select
          :items="this.accessmethod_options"
          v-model="method_id"
          @change="onChange"
          label="Access Method"
          placeholder="Filter by Access Method"
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
        accessmethods: { type:Array, default: () => [] },
    },
    data() {
      return {
        method_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (method) {
        var self = this;
        this.$store.dispatch('updateAccessMethodFilter', method);
// -- Hard-wired for testing
this.$store.dispatch('updateMasterId', 1);
// --
        axios.post('/update-report-filters', {
            filters: self.all_filters,
            master_id: self.master_id,
        })
        .then( function(response) {
            self.$store.dispatch('updateAccessTypeOptions',response.data.filters.accesstypes);
            self.$store.dispatch('updateDataTypeOptions',response.data.filters.datatypes);
            self.$store.dispatch('updateInstitutionOptions',response.data.filters.institutions);
            self.$store.dispatch('updatePlatformOptions',response.data.filters.platforms);
            self.$store.dispatch('updateProviderOptions',response.data.filters.providers);
            self.$store.dispatch('updateSectionTypeOptions',response.data.filters.sectiontypes);
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id','accessmethod_options'])
    },
    mounted() {
      if (!this.accessmethod_options.length) {
          this.$store.dispatch('updateAccessMethodOptions',this.accessmethods);
      }
    }
  }
</script>

<style>
</style>
