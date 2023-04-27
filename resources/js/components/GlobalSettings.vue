<template>
  <div class="d-flex mt-2">
    <form>
      <v-row class="d-flex ma-0" no-gutters>
        <template v-for="key in Object.keys(this.mutable_settings)">
          <v-col class="d-flex px-2 justify-end" cols="2"><strong>{{ key }}</strong></v-col>
          <v-col class="d-flex px-2" cols="4">
            <v-text-field v-model="mutable_settings[key]" :label="key" outlined dense></v-text-field>
          </v-col>
        </template>
      </v-row>
      <v-row v-if="success!=''" class="status-message">
        <span class="good" role="alert" v-text="success"></span>
      </v-row>
      <v-row v-if="failure!=''" class="status-message">
        <span class="fail" role="alert" v-text="failure"></span>
      </v-row>
      <v-row class="d-flex ma-0" no-gutters>
        <v-col class="d-flex pa-0 justify-center">
          <v-btn small color="primary" type="button" @click="formSubmit()">Update All Globals</v-btn>
        </v-col>
      </v-row>
    </form>
  </div>
</template>
<script>
  import Form from '@/js/plugins/Form';
  window.Form = Form;
  export default {
    props: {
      settings: { type:Object, default: () => {} },
    },
    data () {
      return {
        success: '',
        failure: '',
        mutable_settings: { ...this.settings },
      }
    },
    methods: {
        formSubmit (event) {
            this.success = '';
            this.failure = '';
            axios.post('/global/config', {
                all_globals: this.mutable_settings
            })
            .then( (response) => {
                if (response.data.result) {
                    this.success = response.data.msg;
                } else {
                    this.failure = response.data.msg;
                }
            })
            .catch(error => {});
        },
    },
    mounted() {
      console.log('Global Settings component mounted.');
    }
  }
</script>
<style>
</style>
