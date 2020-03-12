<template>
  <div class="form-field">
    <v-select
          :items="accessmethods"
          v-model="method_id"
          @change="onChange"
          label="Access Method"
          placeholder="Filter by Access Method"
          item-text="name"
          item-value="id"
    ></v-select>
  </div>
</template>

<script>
  export default {
    props: {
        accessmethods: { type:Array, default: () => [] },
    },
    data() {
      return {
        method_id: { type:Number, default:0 },
      }
    },
    methods: {
      onChange (method) {
        this.$store.dispatch('updateAccessMethodFilter', method);
      },
    },
  }
</script>

<style>
</style>
