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
        this.$store.dispatch('updateDataTypeFilter', type);
      },
    },
  }
</script>

<style>
</style>
