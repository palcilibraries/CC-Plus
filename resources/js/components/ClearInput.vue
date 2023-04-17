<script>
    export default {
        data() {
            return {
                length: 0
            }
        },
        methods: {
            clearInput(name) {
                const input = this.$refs[name];
                input.value = '';
            }
        }
    }
</script>
