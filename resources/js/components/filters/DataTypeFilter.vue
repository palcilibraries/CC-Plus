<template>
  <div class="form-field">
    <v-select
          :items="datatypes"
          v-model="type_id"
          @change="onChange"
          label="Data Type"
          placeholder="Filter by Data Type"
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
        datatypes: { type:Array, default: () => [] },
    },
    data() {
      return {
        type_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (type) {
        var self = this;
        this.$store.dispatch('updateDataTypeFilter', type);
// -- Hard-wired for testing
this.$store.dispatch('updateMasterReport', 'TR');
// --
        axios.post('/update-report-filters', {
            filters: self.all_filters,
            master_report: self.master_report,
        })
        .then( function(response) {
            output = response.data;
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_report'])
    },
  }
</script>

<style>
</style>
