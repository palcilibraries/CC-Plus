<template>
  <div>
    <v-select v-if="is_manager || is_admin"
          :items="unset"
          @change="onUnsetChange"
          placeholder="Connect a Provider"
          item-text="name"
          item-value="id"
          outlined
    ></v-select>
    <v-data-table :headers="headers" :items="mutable_settings" item-key="id" class="elevation-1">
      <template v-slot:item="{ item }" >
        <tr>
          <td><a :href="'/providers/'+item.provider.id+'/edit'">{{ item.provider.name }}</a></td>
          <td>{{ item.customer_id }}</td>
          <td>{{ item.requestor_id }}</td>
          <td>{{ item.API_key }}</td>
          <td><v-btn class='btn btn-danger' small type="button" @click="destroy(item.id)">Delete</v-btn></td>
        </tr>
      </template>
      <tr><td colspan="5">&nbsp;</td></tr>
    </v-data-table>
    <div>
      <span class="good" role="alert" v-text="success"></span>
      <span class="fail" role="alert" v-text="failure"></span>
    </div>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Swal from 'sweetalert2';
    import axios from 'axios';
    export default {
        props: {
                settings: { type:Array, default: () => {} },
                unset: { type:Array, default: () => {} },
               },
        data() {
            return {
                success: '',
                failure: '',
                mutable_settings: this.settings,
                headers: [
                  { text: 'Name ', value: 'name' },
                  { text: 'Customer ID', value: 'customer_id' },
                  { text: 'Requestor ID', value: 'requestor_id' },
                  { text: 'API Key', value: 'API_key' },
                  { text: '', value: ''}
                ],
            }
        },
        methods: {
            destroy (settingid) {
                var self = this;
                Swal.fire({
                  title: 'Are you sure?',
                  text: "Deleting these settings cannot be reversed, only manually recreated.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#3085d6',
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes, proceed'
                }).then((result) => {
                  if (result.value) {
                      axios.delete('/sushisettings/'+settingid)
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
                       this.mutable_settings.splice(this.mutable_settings.findIndex(u=> u.id == userid),1);
                  }
                })
                .catch({});
            },
            onUnsetChange (prov) {
                window.location.href = "/providers/"+prov+"/edit";
            },
        },
        computed: {
          ...mapGetters(['is_admin','is_manager'])
        },
        mounted() {
            console.log('Providers-by-Inst Component mounted.');
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
