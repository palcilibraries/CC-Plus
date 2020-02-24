<template>
  <div>
    <span class="form-good" role="alert" v-text="confirm"></span>
    <v-app providerform>
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
                        <v-text-field v-model="form.server_url_r5" label="SUSHI Service URL:" outlined></v-text-field>
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
        </form>
    </v-app>
  </div>
</template>

<script>
    export default {
        props: {
                provider: { type:Object, default: () => {} },
                institutions: { type:Array, default: () => [] },
                master_reports: { type:Array, default: () => [] },
                provider_reports: { type:Array, default: () => [] },
               },

        data() {
            return {
                confirm: '',
                status: ['Inactive','Active'],
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
                var self = this;
                this.form.patch('/providers/'+self.provider['id'])
                    .then( function(response) {
                        self.confirm = 'Provider settings updated.';
                    });
            },
        },
        mounted() {
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
</style>
