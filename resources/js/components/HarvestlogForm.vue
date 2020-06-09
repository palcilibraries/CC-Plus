<template>
  <div class="details">
  <h3 class="section-title">Harvest Details</h3>
    <v-simple-table>
      <tr>
        <td>Institution </td>
        <td>{{ mutable_harvest.sushi_setting.institution.name }}</td>
      </tr>
      <tr>
        <td>Provider </td>
        <td>{{ mutable_harvest.sushi_setting.provider.name }}</td>
      </tr>
      <tr>
        <td>Report </td>
        <td>{{ mutable_harvest.report.name }}</td>
      </tr>
      <tr>
        <td>Usage Month </td>
        <td>{{ mutable_harvest.yearmon }}</td>
      </tr>
      <tr>
        <td>Attempts </td>
        <td>{{ mutable_harvest.attempts }}</td>
      </tr>
      <tr>
        <td>Status </td>
        <td v-if="(is_admin || is_manager) && statusvals.indexOf(mutable_harvest.status) > -1">
          </thead>
          <v-select :items="statusvals" v-model="mutable_harvest.status" value="mutable_harvest.status" dense outline
                    @change="updateStatus()"
          ></v-select>
          <span>
            <strong>Note: </strong><em>Updating a harvest status to "Retrying" will also set attempts
            to zero and cause this harvest to run during the next scheduled overnight harvest process.</em>
          </span>
        </td>
        <td v-else>{{ mutable_harvest.status }}</td>
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
                mutable_harvest: this.harvest,
            }
        },
        methods: {
            updateStatus() {
                axios.patch('/harvestlogs/'+this.harvest['id'], {
                    status: this.mutable_harvest.status
                })
                .then((response) => {
                    if (response.data.result) {
                        this.mutable_harvest = response.data.harvest;
                    }
                })
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
