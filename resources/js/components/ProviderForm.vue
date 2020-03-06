<template>
  <div>
    <div v-if="is_admin || can_edit">
      <form method="POST" action="" @submit.prevent="formSubmit" @keydown="form.errors.clear($event.target.name)">
        <v-container grid-list-xl>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-text-field v-model="form.name" label="Name" outlined></v-text-field>
            </v-col>
          </v-row>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-switch v-model="form.is_active" label="Active?"></v-switch>
            </v-col>
          </v-row>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-select
                  :items="institutions"
                  v-model="form.inst_id"
                  value="provider.inst_id"
                  label="Serves"
                  item-text="name"
                  item-value="id"
                  outlined
              ></v-select>
            </v-col>
          </v-row>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL" outlined></v-text-field>
            </v-col>
          </v-row>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-subheader v-text="'Run Harvests Monthly on Day'"></v-subheader>
              <v-text-field v-model="form.day_of_month"
                            label="Day-of-Month"
                            hide-details
                            single-line
                            type="number"
              ></v-text-field>
            </v-col>
          </v-row>
          <v-row>
            <v-col class="d-flex" cols="12" sm="6">
              <v-subheader v-text="'Reports to Harvest'"></v-subheader>
              <v-select
                  :items="master_reports"
                  v-model="form.master_reports"
                  value="provider_reports"
                  item-text="name"
                  item-value="id"
                  label="Select"
                  multiple
                  chips
                  hint="Choose which reports to harvest"
                  persistent-hint
              ></v-select>
            </v-col>
          </v-row>
          <v-row align="center">
            <v-flex md3>
              <v-btn small color="primary" type="submit" :disabled="form.errors.any()">
                  Save Provider Settings
              </v-btn>
            </v-flex>
          </v-row>
        </v-container>
        <span class="form-good" role="alert" v-text="success"></span>
        <span class="form-fail" role="alert" v-text="failure"></span>
      </form>
    </div>
    <!-- not manager -->
    <div v-else>
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
    </div>
  </div>
</template>

<script>
    import { mapGetters } from 'vuex'
    import Form from '@/js/plugins/Form';
    window.Form = Form;

    export default {
        props: {
                provider: { type:Object, default: () => {} },
                prov_inst_name: { type:String, default: '' },
                institutions: { type:Array, default: () => [] },
                master_reports: { type:Array, default: () => [] },
                provider_reports: { type:Array, default: () => [] },
               },

        data() {
            return {
                success: '',
                failure: '',
                status: '',
                statusvals: ['Inactive','Active'],
                inst_name: '',
                can_edit: false,
                form: new window.Form({
                    name: this.provider.name,
                    inst_id: this.provider.inst_id,
                    is_active: this.provider.is_active,
                    server_url_r5: this.provider.server_url_r5,
                    day_of_month: this.provider.day_of_month,
                    master_reports: this.provider_reports
                })
            }
        },
        methods: {
            formSubmit (event) {
                this.success = '';
                this.failure = '';
                var self = this;
                this.form.patch('/providers/'+self.provider['id'])
                    .then( function(response) {
                        if (response.result) {
                            self.success = response.msg;
                        } else {
                            self.failure = response.msg;
                        }
                    });
            },
        },
        computed: {
          ...mapGetters(['is_manager','is_admin','user_inst_id'])
        },
        mounted() {
            if ( this.provider.inst_id==1 ) {
                this.inst_name="Entire Consortium";
            } else {
                this.inst_name = this.prov_inst_name;
            }
            if ( this.is_manager && this.provider.inst_id==this.user_inst_id) {
                this.can_edit = true;
            } else {
                this.can_edit = false;
            }
            this.status=this.statusvals[this.provider.is_active];
            console.log('Provider Component mounted.');
        }
    }
</script>

<style>
.form-good {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: green;
}
.form-fail {
    position: relative;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
    color: red;
}
</style>
