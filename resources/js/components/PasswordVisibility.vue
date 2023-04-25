<script>
    export default {
        data() {
            return {
                visible: false
            }
        },
        methods: {
            toggleVisibility(field) {
                var input = this.$refs[field];
                if (input.type === 'password') {
                    input.type = 'text';
                    this.visible = true;
                } else if (input.type === 'text') {
                    input.type = 'password';
                    this.visible = false;
                }
            }
        }
    }
</script>
