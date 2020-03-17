<template>
  <div class="form-field">
    <v-select
          :items="institutiongroups"
          v-model="group_id"
          @change="onChange"
          label="Institution Group"
          placeholder="Filter by Institution Group"
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
        institutiongroups: { type:Array, default: () => [] },
    },
    data() {
      return {
        group_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (group_id) {
        var self = this;
        this.$store.dispatch('updateInstGroupFilter', group_id);
// -- Hard-wired for testing
this.$store.dispatch('updateMasterId', 1);
// --
        axios.post('/update-report-filters', {
            filters: self.all_filters,
            master_id: self.master_id,
        })
        .then( function(response) {
            output = response.data;
        })
        .catch(error => {});
      },
    },
    computed: {
      ...mapGetters(['all_filters','master_id'])
    },
  }
</script>

<style>
</style>
