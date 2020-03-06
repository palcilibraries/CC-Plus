<template>
  <v-data-table :headers="headers" :items="providers" item-key="prov_id" class="elevation-1">
    <template v-slot:item="{ item }">
      <tr>
        <td v-if="is_admin || is_manager">
          <a :href="'/providers/'+item.prov_id+'/edit'">{{ item.prov_name }}</a>
        </td>
        <td v-else>{{ item.prov_name }}</a>
        <td v-if="item.is_active">Active</td>
        <td v-else>Inactive</td>
        <td v-if="item.inst_id==1">Entire Consortium</td>
        <td v-else><a :href="'/institutions/'+item.inst_id+'/edit'">{{ item.inst_name }}</a></td>
        <td>{{ item.day_of_month }}</td>
        <td>&nbsp;</td>
      </tr>
    </template>
  </v-data-table>
</template>

<script>
  import { mapGetters } from 'vuex'
  export default {
    props: {
            providers: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Provider ', value: 'prov_name', align: 'start' },
          { text: 'Status', value: 'is_active' },
          { text: 'Serves', value: 'inst_name' },
          { text: 'Harvest Day', value: 'day_of_month' },
          { text: 'Action', value: '' },
        ],
      }
    },
    computed: {
      ...mapGetters(['is_manager','is_admin'])
    },
    mounted() {
      console.log('ProviderData Component mounted.');
    }
  }
</script>
<style>
</style>
