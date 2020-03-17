<template>
  <div class="form-field">
    <v-select
          :items="this.provider_options"
          v-model="prov_id"
          @change="onChange"
          label="Provider"
          placeholder="Filter by Provider"
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
        providers: { type:Array, default: () => [] },
    },
    data() {
      return {
        prov_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (prov) {
        var self = this;
        this.$store.dispatch('updateProviderFilter', prov);
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
            self.$store.dispatch('updateSectionTypeOptions',response.data.filters.sectiontypes);
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id','provider_options'])
    },
    mounted() {
      if (!this.provider_options.length) {
          this.$store.dispatch('updateProviderOptions',this.providers);
      }
    }
  }
</script>

<style>
</style>
