if (document.getElementById("sa-admin")) {
    Vue.use(VeeValidate);
    var vm = new Vue({
        el: '#sa-admin',
        created() {
            this.newCountryObject();

            //make sure all payment methods aéxist for checkbox check
            for (const paymentMethodPaid of this.paymentMethods) {
                let found = false;
                for (const i in this.settings.paymentMethodsPaid) {
                    if (i == paymentMethodPaid) {
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    this.settings.paymentMethodsPaid[paymentMethodPaid] = false;
                }
            }
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
                axios.post(sa_settings.ajaxUrl + "?action=sa_save_settings", this.settings).then(
                    res => {
                        this.settings = res.data.settings;
                    });
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
