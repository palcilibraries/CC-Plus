<template>
  <div>
    <v-row>
      <v-col cols="2" sm="1">
        <v-menu ref="menuF" v-model="fromMenu" :close-on-content-click="false" :return-value.sync="YMFrom"
                transition="scale-transition" offset-y max-width="290px" min-width="290px">
          <template v-slot:activator="{ on }">
            <v-text-field v-model="YMFrom" label="From" readonly v-on="on"></v-text-field>
          </template>
          <!-- <v-date-picker v-model="YMFrom" type="month" no-title scrollable> -->
          <v-date-picker v-model="YMFrom" type="month" :min="minym" :max="YMTo" no-title scrollable>
            <v-spacer></v-spacer>
              <v-btn text color="primary" @click="fromMenu = false">Cancel</v-btn>
              <v-btn text color="primary" @click="$refs.menuF.save(YMFrom)">OK</v-btn>
          </v-date-picker>
        </v-menu>
      </v-col>
      <v-col cols="2" sm="1">
        <v-menu ref="menuT" v-model="toMenu" :close-on-content-click="false" :return-value.sync="YMTo"
                transition="scale-transition" offset-y max-width="290px" min-width="290px">
          <template v-slot:activator="{ on }">
            <v-text-field v-model="YMTo" label="To" readonly v-on="on"></v-text-field>
          </template>
          <!-- <v-date-picker v-model="YMTo" type="month" no-title scrollable> -->
          <v-date-picker v-model="YMTo" type="month" :min="YMFrom" :max="maxym" no-title scrollable>
            <v-spacer></v-spacer>
              <v-btn text color="primary" @click="toMenu = false">Cancel</v-btn>
              <v-btn text color="primary" @click="$refs.menuT.save(YMTo)">OK</v-btn>
          </v-date-picker>
        </v-menu>
      </v-col>
    </v-row>
  </div>
</template>

<script>
import { mapGetters } from 'vuex';
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
  computed: {
      ...mapGetters(['filter_by_fromYM', 'filter_by_toYM']),
  },
  mounted() {
    this.YMFrom = this.ymfrom;
    this.YMTo = this.ymto;
    console.log('DateRange Component mounted.');
  }
}
</script>

<style>
</style>
