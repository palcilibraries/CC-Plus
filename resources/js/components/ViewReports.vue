<template>
  <div>
    <h3>User-Defined Reports</h3>
    <v-data-table v-if="user_reports.length>0" :headers="user_headers" :items="user_reports" item-key="id"
                  disable-sort class="elevation-1" :hide-default-footer="hide_user_footer">
      <template v-slot:item="{ item }">
        <tr>
          <td><a :href="'/savedreports/'+item.id+'/edit'">{{ item.title }}</a></td>
          <td>{{ item.master.name }}</td>
          <td>{{ item.months }}</td>
          <td>{{ item.field_count }}</td>
          <td><v-btn class='btn' x-small type="button" :href="'/reports/preview?saved_id='+item.id">Preview</v-btn></td>
        </tr>
      </template>
    </v-data-table>
    <p>&nbsp;</p>
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
            user_reports: { type:Array, default: () => [] },
            counter_reports: { type:Array, default: () => [] },
           },
    data () {
      return {
        user_headers: [
          { text: 'Name', value: 'title' },
          { text: 'Based-on (Master)', value: 'name' },
          { text: '#-Months', value: 'months' },
          { text: '#-Fields', value: 'field_count' },
          { text: '', value: '' },
        ],
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
        hide_user_footer: true,
        hide_counter_footer: true,
      }
    },
    mounted() {
      if (this.user_reports.length > 10) this.hide_user_footer = false;
      if (this.counter_reports.length > 20) this.hide_counter_footer = false;
      console.log('ReportView Component mounted.');
    }
  }
</script>
<style>
</style>
