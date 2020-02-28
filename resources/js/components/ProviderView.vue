<template>
  <v-simple-table>
    <tr>
      <td>Name </td>
      <td>{{ provider.name }}</td>
    </tr>
    <tr>
      <td>Serves </td>
      <td>{{ inst_name }}</td>
    </tr>
    <tr>
      <td>Status </td>
      <td>{{ status }}</td>
    </tr>
    <tr>
      <td>Service URL </td>
      <td>{{ provider.server_url_r5 }}</td>
    </tr>
    <tr>
      <td>FTE </td>
      <td>{{ provider.day_of_month }}</td>
    </tr>
    <tr>
      <td>Reports </td>
      <td>
        <template v-for="report in master_reports">
          <v-chip v-if="provider_reports.includes(report.id)">
            {{ report.name }}
         </v-chip>
        </template>
      </td>
    </tr>
  </v-simple-table>
</template>

<script>
    export default {
        props: {
                provider: { type:Object, default: () => {} },
                institution: { type:String, default: '' },
                master_reports: { type:Array, default: () => [] },
                provider_reports: { type:Array, default: () => [] },
               },
        data() {
            return {
                status: '',
                inst_name: '',
                statusvals: ['Inactive','Active'],
            }
        },
        mounted() {
            if ( this.institution=="Consortium Staff" ) {
                this.inst_name="Entire Consortium";
            } else {
                this.inst_name=this.institution;
            }
            this.status=this.statusvals[this.provider.is_active];
            console.log('Provider Component mounted.');
        }
    }
</script>

<style>
</style>
