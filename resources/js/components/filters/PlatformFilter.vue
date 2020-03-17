<template>
  <div class="form-field">
    <v-select
          :items="this.platform_options"
          v-model="plat_id"
          @change="onChange"
          label="Platform"
          placeholder="Filter by Platform"
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
        platforms: { type:Array, default: () => [] },
    },
    data() {
      return {
        plat_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (plat) {
        var self = this;
        this.$store.dispatch('updatePlatformFilter', plat);
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
            self.$store.dispatch('updateProviderOptions',response.data.filters.providers);
            self.$store.dispatch('updateSectionTypeOptions',response.data.filters.sectiontypes);
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id','platform_options'])
    },
    mounted() {
      if (!this.platform_options.length) {
          this.$store.dispatch('updatePlatformOptions',this.platforms);
      }
    }
  }
</script>

<style>
</style>
