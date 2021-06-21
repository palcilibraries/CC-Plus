<template>
  <div>
    <v-expansion-panels multiple focusable :value="panel">
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h3>Attempts</h3>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <v-simple-table>
            <thead>
              <tr>
                <th>Attempted</th>
                <th>Process Step</th>
                <th>Error Code</th>
                <th>Severity</th>
                <th>Message</th>
                <th>Details</th>
              </tr>
            </thead>
            <template v-for="attempt in attempts">
              <tbody>
                <tr>
                  <td>{{ attempt.attempted }}</td>
                  <td>{{ attempt.process_step }}</td>
                  <td>{{ attempt.error_id }}</td>
                  <td>{{ attempt.severity }}</td>
                  <td>{{ attempt.message }}</td>
                  <td>{{ attempt.detail }}</td>
                </tr>
              </tbody>
            </template>
          </v-simple-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  export default {
    props: {
            attempts: { type:Array, default: () => [] },
    },
    data() {
        return {
            panel: [0],     // default to first panel is open
        }
    },
    mounted() {
        if (typeof(this.attempts[0].process_step) != 'undefined') {
            if (this.attempts[0].process_step == 'SUCCESS') {
                this.panel = [];
            }
        }
    },
  }
</script>
