<script setup>
import { computed, onMounted, ref } from 'vue';
import { useClinicContextStore } from '../../stores/clinicContext';
import { clinicSettingsService } from '../../services/clinicSettings';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';

const props = defineProps({
    patientId: { type: Number, default: null },
});

const context = useClinicContextStore();
const busy = ref(false);
const error = ref(null);
const settings = ref(null);

const billing = computed(() => settings.value?.sections?.billing?.values ?? {});
const paymentMethods = computed(() => {
    const list = billing.value.payment_methods ?? [];
    return Array.isArray(list) && list.length ? list : ['cash'];
});

async function load() {
    busy.value = true;
    error.value = null;
    try {
        const response = await clinicSettingsService.get();
        settings.value = response.data.data;
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    <span>{{ props.patientId ? 'Patient Billing' : 'Billing' }}</span>
                </p>
                <h1>Billing</h1>
                <p>Configure invoice and payment preferences for the clinic.</p>
            </div>
            <RouterLink v-if="context.allowed('settings') && context.can('clinic_settings.update')" class="btn" to="/app/settings/billing">
                Open Billing Settings
            </RouterLink>
        </div>

        <FormErrors :error="error" />

        <div v-if="busy" class="clinic-panel p-6" role="status">Loading billing preferences…</div>
        <template v-else>
            <p v-if="props.patientId" class="clinic-trial">Patient billing records are not available in this release yet. This screen shows the clinic’s billing configuration and next-step setup.</p>

            <div class="clinic-kpis">
                <section class="clinic-panel clinic-kpi">
                    <span class="clinic-kpi-icon mint"><AppIcon name="revenue" :size="28" /></span>
                    <div>
                        <h2>Default Consultation Fee</h2>
                        <strong>{{ settings?.summary?.plan?.currency || context.data?.plan?.currency || 'USD' }} {{ Number(billing.consultation_fee || 0).toFixed(2) }}</strong>
                        <p>Current clinic value</p>
                    </div>
                </section>
                <section class="clinic-panel clinic-kpi">
                    <span class="clinic-kpi-icon blue"><AppIcon name="audit" :size="28" /></span>
                    <div>
                        <h2>Invoice Prefix</h2>
                        <strong>{{ billing.invoice_prefix || 'INV-' }}</strong>
                        <p>Future invoice numbering</p>
                    </div>
                </section>
                <section class="clinic-panel clinic-kpi">
                    <span class="clinic-kpi-icon violet"><AppIcon name="receipt" :size="28" /></span>
                    <div>
                        <h2>Receipt Prefix</h2>
                        <strong>{{ billing.receipt_prefix || 'RCT-' }}</strong>
                        <p>Future receipt numbering</p>
                    </div>
                </section>
                <section class="clinic-panel clinic-kpi">
                    <span class="clinic-kpi-icon green"><AppIcon name="calendar" :size="28" /></span>
                    <div>
                        <h2>Payment Methods</h2>
                        <strong>{{ paymentMethods.length }}</strong>
                        <p>Enabled for billing</p>
                    </div>
                </section>
            </div>

            <section class="clinic-panel">
                <div class="clinic-card-heading">
                    <h2>Billing configuration</h2>
                    <RouterLink v-if="context.allowed('settings')" class="btn-secondary" to="/app/settings/billing">Manage settings</RouterLink>
                </div>

                <div class="clinic-info">
                    <div>
                        <AppIcon name="revenue" :size="24" />
                        <span>
                            Invoice number length
                            <small>{{ Number(billing.number_length || 6) }} digits</small>
                        </span>
                    </div>
                    <div>
                        <AppIcon name="activity" :size="24" />
                        <span>
                            Default tax rate
                            <small>{{ Number(billing.tax_rate || 0) }}%</small>
                        </span>
                    </div>
                    <div>
                        <AppIcon name="roles" :size="24" />
                        <span>
                            Discount policy
                            <small class="capitalize">{{ billing.discount_policy || 'disabled' }}</small>
                        </span>
                    </div>
                    <div>
                        <AppIcon name="members" :size="24" />
                        <span>
                            Allowed payment methods
                            <small>{{ paymentMethods.join(', ') }}</small>
                        </span>
                    </div>
                </div>
            </section>

            <section class="clinic-panel">
                <div class="clinic-card-heading">
                    <h2>Billing workflow status</h2>
                </div>
                <p class="mt-3 leading-7 text-slate-500">
                    Billing preferences are now available in the clinic settings, but invoice and receipt creation are still planned for a future module stage.
                    This screen gives the clinic a working billing landing page while the rest of the finance workflow is developed.
                </p>
            </section>
        </template>
    </div>
</template>
