<template>
  <div>
    <h2>Recent Activity</h2>
    <v-data-table :headers="headers" :items="mutable_harvests" item-key="id" class="elevation-1"
                  :hide-default-footer="true" :server-items-length="10" dense>
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.updated_at.substr(0,10) }}</td>
          <td>{{ item.sushi_setting.institution.name }}</td>
          <td>{{ item.sushi_setting.provider.name }}</td>
          <td>{{ item.report.name }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.attempts }}</td>
          <td>{{ item.status }}</td>
          <td v-if="item.attempts>0">
            <a :href="'/harvestlogs/'+item.id">details</a>
          </td>
        </tr>
      </template>
    </v-data-table>
	<p class="more">
		<a href="/harvestlogs">See all recent harvests</a>
	</p>
  </div>
</template>

<script>
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Harvested', value: 'created_at' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: '' },
        ],
        mutable_harvests: this.harvests,
      }
    },
    mounted() {
      console.log('HarvestLogSummary Component mounted.');
    }
  }
</script>
<style>
</style>
