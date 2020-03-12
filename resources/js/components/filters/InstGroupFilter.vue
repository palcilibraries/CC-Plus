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
        this.$store.dispatch('updateInstGroupFilter', group_id);
      },
    },
  }
</script>

<style>
</style>
