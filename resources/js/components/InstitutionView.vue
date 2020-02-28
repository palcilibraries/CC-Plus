<template>
  <v-simple-table>
    <tr>
      <td>Name </td>
      <td>{{ institution.name }}</td>
    </tr>
    <tr>
      <td>Type </td>
      <td>{{ inst_type }}</td>
    </tr>
    <tr>
      <td>Status </td>
      <td>{{ status }}</td>
    </tr>
    <tr>
      <td>FTE </td>
      <td>{{ institution.fte }}</td>
    </tr>
    <tr>
      <td>Groups </td>
      <td>
        <template v-for="group in all_groups">
          <v-chip v-if="inst_groups.includes(group.id)">
            {{ group.name }}
         </v-chip>
        </template>
      </td>
    </tr>
    <tr>
      <td>Notes </td>
      <td>{{ institution.notes }}</td>
    </tr>
  </v-simple-table>
</template>

<script>
    export default {
        props: {
                institution: { type:Object, default: () => {} },
                inst_type: { type:String, default: '' },
                inst_groups: { type:Array, default: () => [] },
                all_groups: { type:Array, default: () => [] },
               },
        data() {
           return {
               status: '',
               statusvals: ['Inactive','Active'],
           }
        },
        mounted() {
            this.status=this.statusvals[this.institution.is_active];
            console.log('Institution Component mounted.');
        }
    }
</script>

<style>
</style>
