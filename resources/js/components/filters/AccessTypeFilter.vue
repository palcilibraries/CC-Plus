<template>
  <div class="form-field">
    <v-select
          :items="this.accesstype_options"
          v-model="type_id"
          @change="onChange"
          label="Access Type"
          placeholder="Filter by Access Type"
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
        accesstypes: { type:Array, default: () => [] },
    },
    data() {
      return {
        type_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (type) {
        var self = this;
        this.$store.dispatch('updateAccessTypeFilter', type);
// -- Hard-wired for testing
this.$store.dispatch('updateMasterId', 1);
// --
        axios.post('/update-report-filters', {
            filters: self.all_filters,
            master_id: self.master_id,
        })
        .then( function(response) {
            self.$store.dispatch('updateAccessMethodOptions',response.data.filters.accessmethods);
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
      ...mapGetters(['all_filters','master_id','accesstype_options'])
    },
    mounted() {
      if (!this.accesstype_options.length) {
          this.$store.dispatch('updateAccessTypeOptions',this.accesstypes);
      }
    }
  }
</script>

<style>
</style>
