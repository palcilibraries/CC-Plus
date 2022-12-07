<template>
  <div>
    <v-data-table :headers="headers" :items="mutable_harvests" item-key="id" class="elevation-1"
                  :hide-default-footer="true" :server-items-length="10" dense disable-sort>
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
            <a :href="'/harvestlogs/'+item.id+'/edit'">details</a>
          </td>
        </tr>
      </template>
    </v-data-table>
	<p class="more">
		<a :href="seemore_url">See all harvests</a>
	</p>
  </div>
</template>

<script>
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            inst_id: { type:Number, default: 0 },
            prov_id: { type:Number, default: 0 },
           },
    data () {
      return {
        headers: [
          { text: 'Updated', value: 'updated_at' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: '', sortable: false },
        ],
        mutable_harvests: this.harvests,
        seemore_url: "/harvestlogs",
      }
    },
    mounted() {
      if (this.inst_id>0 || this.prov_id>0) {
          this.seemore_url+='?';
          if (this.inst_id>0) this.seemore_url += 'inst='+this.inst_id;
          if (this.prov_id>0) {
              if (this.inst_id>0) this.seemore_url+='&';
              this.seemore_url += 'prov='+this.prov_id;
          }
      }
      console.log('HarvestLogSummary Component mounted.');
    }
  }
</script>
<style>
</style>
