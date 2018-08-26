if (document.getElementById("sa-admin")) {
    var vm = new Vue({
        el: '#sa-admin',
        data() {
            return {
                settings: sa_settings.settings,
                paymentMethods: sa_settings.paymentMethods,
            };
        },
        methods: {
            saveSettings() {
                axios.post(sa_settings.ajaxUrl + "?action=sa_save_settings", this.settings);
            }
        },
        computed: {
            requiredTags() {
                return false
            },
            formValid() {
                console.log('check form valid');
                return this.settings.apiKey && this.settings.apiSecret && this.settings.defaultPayment && this.settings.defaultShipping;
            }
        }

    })
}
