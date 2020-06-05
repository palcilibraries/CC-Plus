<template>
  <div class="details">
  <h3 class="section-title">Harvest Details</h3>
    <v-simple-table>
      <tr>
        <td>Institution </td>
        <td>{{ harvest.sushi_setting.institution.name }}</td>
      </tr>
      <tr>
        <td>Provider </td>
        <td>{{ harvest.sushi_setting.provider.name }}</td>
      </tr>
      <tr>
        <td>Report </td>
        <td>{{ harvest.report.name }}</td>
      </tr>
      <tr>
        <td>Usage Month </td>
        <td>{{ harvest.yearmon }}</td>
      </tr>
      <tr>
        <td>Attempts </td>
        <td>{{ harvest.attempts }}</td>
      </tr>
      <tr>
        <td>Status </td>
        <td v-if="(is_admin || is_manager) && statusvals.indexOf(harvest.status) > -1">
          <v-select :items="statusvals" v-model="mutable_status" value="mutable_status" dense outline
                    @change="updateStatus()"
          ></v-select>
        </td>
        <td v-else>{{ harvest.status }}</td>
      </tr>
    </v-simple-table>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                harvest: { type:Object, default: () => {} },
               },
        data() {
            return {
                statusvals: ['Fail', 'Retrying', 'Stopped'],
                mutable_status: this.harvest.status,
            }
        },
        methods: {
            updateStatus() {
                // axios.post('/update-alert-status', {
                //     id: alert.id,
                //     status: alert.status
                // })
                // .catch(error => {});
                // axios.post('/harvestlogs/'+this.harvest['id'], {
                axios.patch('/harvestlogs/'+this.harvest['id'], {
                    status: this.mutable_status
                })
                .then((response) => {})
                .catch(error => {});
            },
        },
        computed: {
          ...mapGetters(['is_manager', 'is_admin'])
        },
        mounted() {
            console.log('HarvestLogForm Component mounted.');
        }
    }
</script>

<style>
</style>
