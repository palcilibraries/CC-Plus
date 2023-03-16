<template>
  <div>
    <h3>Standard COUNTER-5 Reports</h3>
    <v-data-table :headers="counter_headers" :items="counter_reports" item-key="id" disable-sort
                  :options.sync="pagination" class="elevation-1"  :hide-default-footer="hide_counter_footer">
      <template v-slot:item="{ item }">
        <tr>
          <td><a :href="'/reports/'+item.id">{{ item.name }}</a></td>
          <td>{{ item.legend }}</td>
          <td>{{ item.master }}</td>
          <td>{{ item.field_count }}</td>
        </tr>
      </template>
    </v-data-table>
    </v-data-table>
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
            { text: 'Parent', value: 'master.name' },
            { text: '#-Fields', value: 'field_count' },
            { text: '', value: 'data-table-expand' },
        ],
        pagination: {
            page: 1,
            itemsPerPage: 20,
            totalItems: 0
        },
        hide_counter_footer: true,
      }
    },
    mounted() {
      if (this.counter_reports.length > 20) this.hide_counter_footer = false;
      console.log('ReportView Component mounted.');
    }
  }
</script>
<style>
</style>
