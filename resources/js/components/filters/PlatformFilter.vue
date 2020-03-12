<template>
  <div class="form-field">
    <v-select
          :items="platforms"
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
        this.$store.dispatch('updatePlatformFilter', plat);
      },
    },
  }
</script>

<style>
</style>
