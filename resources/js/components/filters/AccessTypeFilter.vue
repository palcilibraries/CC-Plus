<template>
  <div class="form-field">
    <v-select
          :items="accesstypes"
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
        this.$store.dispatch('updateAccessTypeFilter', type);
      },
    },
  }
</script>

<style>
</style>
