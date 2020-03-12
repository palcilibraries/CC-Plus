<template>
  <div class="form-field">
    <v-select
          :items="publishers"
          v-model="pub_id"
          @change="onChange"
          label="Publisher"
          placeholder="Filter by Publisher"
          item-text="name"
          item-value="id"
    ></v-select>
  </div>
</template>

<script>
  export default {
    props: {
        publishers: { type:Array, default: () => [] },
    },
    data() {
      return {
        pub_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (pub) {
        this.$store.dispatch('updatePublisherFilter', pub);
      },
    },
  }
</script>

<style>
</style>
