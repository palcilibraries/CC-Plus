<template>
  <v-data-table :headers="headers" :items="users" item-key="id" class="elevation-1">
    <template v-slot:item="{ item }">
      <tr>
        <td><a :href="'/users/'+item.id+'/edit'">{{ item.name }}</a></td>
        <td><a :href="'/institutions/'+item.inst_id+'/edit'">{{ item.inst }}</a></td>
        <td v-if="item.is_active">Active</td>
        <td v-else>Inactive</td>
        <td>{{ item.email }}</td>
        <td>{{ item.roles }}</td>
        <td>{{ item.last_login }}</td>
      </tr>
    </template>
  </v-data-table>
</template>

<script>
  export default {
    props: {
            users: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'User Name ', value: 'name', align: 'start' },
          { text: 'Institution', value: 'inst' },
          { text: 'Status', value: 'is_active' },
          { text: 'Email', value: 'email' },
          { text: 'Roles', value: 'roles' },
          { text: 'Last Login', value: 'last_login' },
        ],
      }
    },
    mounted() {
      console.log('UserData Component mounted.');
    }
  }
</script>
<style>
</style>
