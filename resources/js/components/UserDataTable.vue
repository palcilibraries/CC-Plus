<template>
  <div>
    <v-data-table :headers="headers" :items="mutable_users" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }">
        <tr>
          <td><a :href="'/users/'+item.id+'/edit'">{{ item.name }}</a></td>
          <td><a :href="'/institutions/'+item.inst_id+'/edit'">{{ item.institution.name }}</a></td>
          <td v-if="item.is_active">Active</td>
          <td v-else>Inactive</td>
          <td>{{ item.email }}</td>
          <td>{{ item.roles }}</td>
          <td>{{ item.last_login }}</td>
          <td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete</v-btn></td>
        </tr>
      </template>
    </v-data-table>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import axios from 'axios';
  // window.axios = axios;
  export default {
    props: {
            users: { type:Array, default: () => [] },
           },
    data () {
      return {
        success: '',
        failure: '',
        mutable_users: this.users,
        headers: [
          { text: 'User Name ', value: 'name' },
          { text: 'Institution', value: 'inst' },
          { text: 'Status', value: 'is_active' },
          { text: 'Email', value: 'email' },
          { text: 'Roles', value: 'roles' },
          { text: 'Last Login', value: 'last_login' },
        ],
      }
    },
    methods: {
        destroy (userid) {
            var self = this;
            Swal.fire({
              title: 'Are you sure?',
              text: "All information related to this user will also be deleted!",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
              if (result.value) {
                  axios.delete('/users/'+userid)
                       .then( (response) => {
                           if (response.data.result) {
                               self.failure = '';
                               self.success = response.data.msg;
                           } else {
                               self.success = '';
                               self.failure = response.data.msg;
                           }
                       })
                       .catch({});
                   this.mutable_users.splice(this.mutable_users.findIndex(u=> u.id == userid),1);
              }
            })
            .catch({});
        },
    },
    mounted() {
      console.log('UserData Component mounted.');
    }
  }
</script>
<style>
</style>
