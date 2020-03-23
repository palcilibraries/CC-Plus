<template>
  <div>
    <v-data-table :headers="headers" :items="mutable_users" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td><a :href="'/users/'+item.id+'/edit'">{{ item.name }}</a></td>
          <td>{{ item.permission }}&nbsp;</td>
          <td>{{ item.last_login }}</td>
		  <td><a class="btn btn-primary v-btn v-btn--contained theme--light v-size--small" :href="'/users/'+item.id+'/edit'">edit user</a></td>
          <!--<td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete</v-btn></td>-->
        </tr>
      </template>
      <tr>
        <td colspan="6">
        </td>
      </tr>
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
    export default {
        props: {
                users: { type:Array, default: () => {} },
               },
        data() {
            return {
                success: '',
                failure: '',
                mutable_users: this.users,
                headers: [
                  { text: 'Name ', value: 'name' },
                  { text: 'Permission Level', value: '' },
                  { text: 'Last Login', value: 'last_login' },
                  { text: '', value: ''}
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
            console.log('User Component mounted.');
        }
    }
</script>

<style>
.good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
