<template>
  <div>
    <h3 class="section-title">Attempts</h3>
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
  </div>
</template>
<script>
  export default {
    props: {
            attempts: { type:Array, default: () => [] },
    },
  }
</script>
