<template>
  <v-data-table :headers="headers" :items="institutions" item-key="id" class="elevation-1">
    <template v-slot:item="{ item }">
      <tr>
        <td><a :href="'/institutions/'+item.id+'/edit'">{{ item.name }}</a></td>
        <td>{{ item.type }}</td>
        <td v-if="item.is_active">Active</td>
        <td v-else>Inactive</td>
        <td>{{ item.groups }}</td>
        <td>&nbsp;</td>
      </tr>
    </template>
  </v-data-table>
</template>

<script>
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Institution ', value: 'name', align: 'start' },
          { text: 'Type', value: 'type' },
          { text: 'Status', value: 'is_active' },
          { text: 'Group(s)', value: 'groups' },
          { text: 'Action', value: '' },
        ],
      }
    },
    mounted() {
      console.log('InstitutionData Component mounted.');
    }
  }
</script>
<style>
</style>
