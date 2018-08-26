if (document.getElementById("sa-admin")) {
    Vue.use(VeeValidate);
    var vm = new Vue({
        el: '#sa-admin',
        created() {
            this.newCountryObject();
        },
        data() {
            return {
                settings: sa_settings.settings,
                paymentMethods: sa_settings.paymentMethods
            };
        },
        methods: {
            newCountryObject() {
                this.settings.countryObjects.push({country: "", object_id: ""});
            },
            removeCountryObject(id) {
                this.settings.countryObjects.splice(id, 1);
            },
            saveSettings() {
                axios.post(sa_settings.ajaxUrl + "?action=sa_save_settings", this.settings);
            }
        },
        computed: {
            requiredTags() {
                return false
            },
            formValid() {
                return this.formFieldsValidated && this.settings.apiKey && this.settings.apiSecret && this.settings.defaultPayment && this.settings.defaultShipping;
            },
            formFieldsValidated() {
                return this.errors.items.length <= 0;
            }
        }

    })
}
