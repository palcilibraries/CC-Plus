<template>
  <div>
    <v-expansion-panels focusable v-model="panels">
      <v-expansion-panel>
  	    <v-expansion-panel-header>
          <h2>COUNTER Report Types</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <div class="d-flex pt-1"></div>
          <v-tabs v-model="tab" grow hide-slider class="custom-tabs">
            <v-tab v-for="report in counter_reports" :key="report.series" class="custom-tab">
              {{ report.series }}
            </v-tab>
            <v-tab-item v-model="tab">
              <div v-if="counter_reports[tab]['text'].length>0" class="d-flex py-2">
                <span><strong>{{ counter_reports[tab]['text'] }}</strong></span>
              </div>
              <div v-if="counter_reports[tab]['reports'].length>0">
                <v-data-table :headers="counter_headers" :items="counter_reports[tab]['reports']" item-key="id" disable-sort
                              :options.sync="pagination" class="elevation-1" :hide-default-footer="hide_counter_footer"
                              :expanded="expanded" @click:row="expandRow" show-expand>
                  <template v-slot:expanded-item="{ item }">
                    <td :colspan="counter_headers.length">
                      <div class="detail-hdr">Report Fields and Filters</div>
                      <div v-for="field in item.fields" :key="field.name" class="report-field">
                        {{ field.name }}
                        <span v-if="field.filter.length>0"> : <strong>{{ field.filter }} </strong></span>
                      </div>
                      <p>&nbsp;</p>
                    </td>
                  </template>
                </v-data-table>
              </div>
            </v-tab-item>
          </v-tabs>
  	    </v-expansion-panel-content>
	    </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  export default {
    props: {
            counter_reports: { type:Array, default: () => [] },
           },
    data () {
      return {
        counter_headers: [
            { text: 'Name', value: 'name' },
            { text: 'Description', value: 'legend' },
            { text: '#-Fields', value: 'field_count' },
            { text: '', value: 'data-table-expand' },
        ],
        pagination: {
            page: 1,
            itemsPerPage: 20,
            totalItems: 0
        },
        hide_counter_footer: true,
        tab: 0,
        panels: [0],     // default to first panel is open
        expanded: [],
      }
    },
    methods: {
      expandRow (item) {
        this.expanded = item === this.expanded[0] ? [] : [item]
      },
    },
    mounted() {
      if (this.counter_reports.length > 20) this.hide_counter_footer = false;
      console.log('ReportView Component mounted.');
    }
  }
</script>
<style scoped>
.custom-tab {
    float: left;
    cursor: pointer;
    padding: 12px 24px;
    border: 1px solid #ccc;
    border-right: none;
    background-color: #f1f1f1;
    border-radius: 10px 10px 0 0;
}
.report-field {
  float: left;
  display: inline-block;
  width: 25%;
  padding: 0px 10px;
}
.detail-hdr {
  float: left;
  display: block;
  width: 100%;
  padding: 10px 10px;
  text-align: center;
  font-weight: bold;
}
</style>
