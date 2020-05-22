<template>
  <div>
    <div v-if="filterable">
<!--
      <p>Filters -go here-</p>
-->
    </div>
    <v-data-table :headers="headers" :items="harvests" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.created_at }}</td>
          <td>{{ item.report.name }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.status }}</td>
          <td v-if="item.attempts>0"><a :href="'/harvestlogs/'+item.id">details</a></td>
        </tr>
      </template>
    </v-data-table>
  </div>
</template>

<script>
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            filterable: { type:Number, default:0 },
           },
    data () {
      return {
        headers: [
          { text: 'Harvest Date ', value: 'harvest_date' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Status', value: 'status' },
          { text: '', value: '' },
        ],
      }
    },
    mounted() {
      console.log('HarvestLogData Component mounted.');
    }
  }
</script>
<style>
</style>
