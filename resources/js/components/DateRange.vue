<template>
  <div>
    <v-row class="d-flex mb-0 pa-0" no-gutters>
      <v-col class="d-flex pa-2" cols="8" sm="4">
        <v-menu ref="menuF" v-model="fromMenu" :close-on-content-click="true" transition="scale-transition"
                offset-y max-width="290px" min-width="290px">
          <template v-slot:activator="{ on }">
            <v-text-field v-model="YMFrom" label="From" readonly v-on="on"></v-text-field>
          </template>
          <v-date-picker v-model="YMFrom" type="month" :min="minym" :max="YMTo" no-title scrollable></v-date-picker>
        </v-menu>
      </v-col>
      <v-col class="d-flex pa-2" cols="8" sm="4">
        <v-menu ref="menuT" v-model="toMenu" :close-on-content-click="true" transition="scale-transition"
                offset-y max-width="290px" min-width="290px">
          <template v-slot:activator="{ on }">
            <v-text-field v-model="YMTo" label="To" readonly v-on="on"></v-text-field>
          </template>
          <v-date-picker v-model="YMTo" type="month" :min="YMFrom" :max="maxym" no-title scrollable></v-date-picker>
        </v-menu>
      </v-col>
    </v-row>
  </div>
</template>

<script>
export default {
  props: {
      minym: { type:String, default: '' },
      maxym: { type:String, default: '' },
      ymfrom: { type:String, default: '' },
      ymto: { type:String, default: '' },
  },
  data () {
    return {
      fromMenu: false,
      toMenu: false,
      YMFrom: this.ymfrom,
      YMTo: this.ymto
    }
  },
  watch: {
    YMFrom: function (newVal) {
        this.$store.dispatch('updateFromYM',newVal);
    },
    YMTo: function (newVal) {
        this.$store.dispatch('updateToYM',newVal);
    },
  },
  mounted() {
    this.$store.dispatch('updateFromYM',this.ymfrom);
    this.$store.dispatch('updateToYM',this.ymto);
    console.log('DateRange Component mounted.');
  }
}
</script>

<style>
</style>
