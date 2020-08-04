<template>
  <div>
    <h3 class="section-title">Failed Attempts</h3>
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
      <template v-for="fail in failed_harvests">
        <tbody>
          <tr>
            <td>{{ fail.created_at }}</td>
            <td>{{ fail.process_step }}</td>
            <td>{{ fail.error_id }}</td>
            <td>{{ fail.ccplus_error.severity.name }}</td>
            <td>{{ fail.ccplus_error.message }}</td>
            <td>{{ fail.detail }}</td>
          </tr>
        </tbody>
      </template>
    </v-simple-table>
  </div>
</template>
<script>
  export default {
    props: {
            failed_harvests: { type:Array, default: () => [] },
           },
  }
</script>
