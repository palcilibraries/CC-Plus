<template>
  <div>
  	<form method="POST" action="/sushisettings" @submit.prevent="formSubmit"
  	      @keydown="form.errors.clear($event.target.name)">
      <v-row v-if="is_admin">
        <v-col class="ma-2" cols="3" sm="3">
          <v-select
            :items="institutions"
            v-model="form.inst_id"
            @change="onInstChange"
            label="Institution(s)"
            item-text="name"
            item-value="id"
            hint="Institution(s) to Harvest"
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-else>
        <v-col class="ma-2" cols="6" sm="4">
          <h5>Institution : {{ inst_name }}</h5>
        </v-col>
      </v-row>
      <v-row v-if="available_providers.length>0">
        <v-col class="ma-2" cols="3" sm="3">
          <v-select
            :items="available_providers"
            v-model="form.prov_id"
            @change="onProvChange"
            label="Provider(s)"
            item-text="name"
            item-value="id"
            hint="Provider(s) to Harvest"
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="available_reports.length>0">
        <v-col class="ma-2" cols="6" sm="4">
          <v-select
            :items="available_reports"
            v-model="form.reports"
            item-text="legend"
            item-value="name"
            label="Report(s) to Harvest"
            multiple
            chips
            hint="Choose which master reports to harvest"
            persistent-hint
          ></v-select>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0">
        <span><h5>Month(s) to Harvest</h5></span>
        <v-col class="ma-2" cols="12">
          <date-range minym="2019-01" :maxym="maxYM" ymfrom="" ymto=""></date-range>
        </v-col>
      </v-row>
      <v-row v-if="form.reports.length>0">
        <v-col class="ma-2" cols="12">
          <span>Queue the harvest(s) to begin</span>
          <v-radio-group v-model="form.when" row>
            <v-radio :label="'Overnight'" value='later'></v-radio>
            <v-radio :label="'Now'" value='now'></v-radio>
          </v-radio-group>
        </v-col>
      </v-row>
      <v-row>
        <v-btn small color="primary" type="submit" :disabled="form.errors.any()">Submit</v-btn>
      </v-row>
    </form>
    <div>
      <span class="form-good" role="alert" v-text="success"></span>
      <span class="form-fail" role="alert" v-text="failure"></span>
    </div>
  </div>
</template>

<script>
  import { mapGetters } from 'vuex';
  export default {
    props: {
            institutions: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            all_reports: { type:Array, default: () => [] },
    },
    data() {
        return {
            success: '',
            failure: '',
            form: new window.Form({
                inst_id: null,
                prov_id: null,
                reports: [],
                fromYM: '',
                toYM: '',
                when: 'later',
            }),
            maxYM: '',
            inst_name: '',
            available_providers: [],
            available_reports: [],
        }
    },
    methods: {
        // Update mutable providers based on inst-change
        onInstChange(instid) {
            if (instid == 0) {
                this.available_providers = this.providers;
            } else {
                this.available_providers = [];
                let inst = this.institutions.find(obj => obj.id == instid);
                inst.sushi_settings.map(s => s.prov_id).forEach(prov => {
                    this.available_providers.push(this.providers.find(obj => obj.id == prov));
                });
                if (this.available_providers.length > 1) {
                    this.available_providers.unshift({id: 0, name: 'All Providers'});
                }
            }
        },
        onProvChange(provid) {
            // Update available reports based on provider-change
            if (provid == 0) {
                this.available_reports = this.all_reports;
            } else {
                this.available_reports = [];
                let prov = this.available_providers.find(obj => obj.id == provid);
                prov.reports.map(r => r.id).forEach(report => {
                    this.available_reports.push(this.all_reports.find(obj => obj.id == report));
                });
            }
        },
        formSubmit (event) {
            if (this.form.reports.length == 0) {
                this.failure = 'No reports selected for harvesting';
                return;
            }
            // Set from/to in the form with values from the data store and check them
            this.form.toYM = this.filter_by_toYM;
            this.form.fromYM = this.filter_by_fromYM;
            if (this.form.toYM == '' && this.form.fromYM != '') this.form.toYM = this.form.fromYM;
            if (this.form.fromYM == '' && this.form.toYM != '') this.form.fromYM = this.form.toYM;
            if (this.form.toYM == '' || this.form.fromYM == '') {
                this.failure = 'Range of months to harvest is invalid';
                return;
            }
            this.form.post('/harvestlogs')
                .then((response) => {
                    if (response.result) {
                        this.failure = '';
                        this.success = response.msg;
                    } else {
                        this.success = '';
                        this.failure = response.msg;
                    }
                });
        },
    },
    computed: {
      ...mapGetters(['is_admin', 'filter_by_toYM', 'filter_by_fromYM']),
    },
    mounted() {
      if ( !this.is_admin ) {
          this.form.inst_id = this.institutions[0].id;
          this.inst_name = this.institutions[0].name;
          this.onInstChange(this.form.inst_id);
      }
      let dt = new Date();
      this.maxYM = dt.getFullYear()+"-"+("0"+(dt.getMonth() + 1));
      console.log('ManualHarvest Component mounted.');
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
