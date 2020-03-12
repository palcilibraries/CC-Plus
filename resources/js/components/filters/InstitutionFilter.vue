<template>
  <div class="form-field">
    <v-select
          :items="institutions"
          v-model="inst_id"
          @change="onChange"
          label="Institution"
          placeholder="Filter by Institution"
          item-text="name"
          item-value="id"
    ></v-select>
  </div>
</template>

<script>
  export default {
    props: {
        institutions: { type:Array, default: () => [] },
    },
    data() {
      return {
        inst_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (inst) {
        this.$store.dispatch('updateInstitutionFilter', inst);
      },
    },
  }
</script>

<style>
</style>
