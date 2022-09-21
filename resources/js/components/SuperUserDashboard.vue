<template>
  <div>
    <h3>Defined Instances</h3>
    <v-data-table :headers="con_headers" :items="consortia" item-key="id" disable-sort
                  :hide-default-footer="hide_user_footer">
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.ccp_key }}</td>
          <td>{{ item.name }}</td>
          <td><v-btn class='btn' x-small type="button" :href="'/preview?saved_id='+item.id">Preview</v-btn></td>
        </tr>
      </template>
    </v-data-table>
  </div>
</template>
<script>
  export default {
    props: {
      consortia: { type:Array, default: () => [] },
      settings: { type:Array, default: () => [] },
    },
    data () {
      return {
        mutable_settings: [...this.settings],
        con_headers: [
            { text: 'Key', value: 'ccp_key' },
            { text: 'Name', value: 'name' },
            { text: '', value: 'data-table-expand' },
        ],
        hide_user_footer: true,
        hide_counter_footer: true,
      }
    },
    computed: {
      settingsChanged() {
          for (var key in this.settings) {
              if (this.mutable_settings[key].value != this.settings[key].value) return true;
          }
          return false;
      },
    },
    mounted() {
      console.log('SuperUser Dashboard mounted.');
    }
  }
</script>
<style>
</style>
