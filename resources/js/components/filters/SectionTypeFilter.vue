<template>
  <div class="form-field">
    <v-select
          :items="sectiontypes"
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
        this.$store.dispatch('updateSectionTypeFilter', type);
      },
    },
  }
</script>

<style>
</style>
