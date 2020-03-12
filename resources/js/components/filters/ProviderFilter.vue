<template>
  <div class="form-field">
    <v-select
          :items="providers"
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
        this.$store.dispatch('updateProviderFilter', prov);
      },
    },
  }
</script>

<style>
</style>
