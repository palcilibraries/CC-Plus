<template>
  <v-container fluid>
    <v-row no-gutters>
      <v-col><h3>{{ report.legend }}</h3></v-col>
    </v-row>
    <v-row no-gutters>
      <v-col>
        <v-simple-table dense>
          <tr v-if="report.parent_id==0" class="d-flex ma-0 align-center">
            <td>Master Report for:</td>
            <td v-for="child in report.children" class="d-flex pa-2">
              <a :href="'/reports/'+child.id">{{ child.name }}</a>
            </td>
          </tr>
          <tr v-else class="d-flex ma-0 align-center">
            <td>
              Report Preset View of : <a :href="'/reports/'+report.parent_id">{{ report.parent.legend }}</a>
            </td>
          </tr>
          <tr class="d-flex mt-4"><td><h5>Report Fields</h5></td></tr>
          <tr v-for="field in fields" no-gutters>
            <td>
              {{ field.legend }}
              <span v-if="typeof(filters[field.qry_as]) != 'undefined'">
                <span v-if="filters[field.qry_as].name == 'All'"> : <strong>All</strong></span>
                <span v-else-if="(filters[field.qry_as].name == '' || filters[field.qry_as].name == null)"></span>
                <span v-else>equal to: <strong>{{ filters[field.qry_as].name }}</strong></span>
              </span>
            </td>
          </tr>
        </v-simple-table>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
    export default {
        props: {
                report:  { type:Object, default: () => {} },
                fields:  { type:Array, default: () => [] },
                filters: { type:Object, default: () => {} },
               },

        data() {
            return {
                latestYear: '',
            }
        },
        methods: {
        },
        mounted() {
            // For filtering by Group, poke the institution legend
            if (this.filters['institution'].legend == 'Institution Group') {
                Object.keys(this.fields).forEach( (key) =>  {
                    if (this.fields[key].legend == 'Institution') {
                        this.fields[key].legend = this.filters['institution'].legend;
                    }
                });
            }
            console.log('ShowCounterReport Component mounted.');
        }
    }
</script>

<style>
</style>
