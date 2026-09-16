import { computed, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useClinicContextStore } from '../stores/clinicContext';
import { billingApi } from '../services/billing';

export function useBillingLedger(customer = () => null) {
    const route = useRoute(), router = useRouter(), auth = useAuthStore(), context = useClinicContextStore();
    const data = ref(null), error = ref(null), busy = ref(false), loading = ref(false);
    const amount = ref(''), method = ref('cash'), reference = ref(''), methods = ref([]);
    let generation = 0, controller, paymentKey = crypto.randomUUID(), previousTenant;
    const invoiceId = computed(() => customer() ? null : route.params.id);
    const ready = () => context.data?.clinic.id === auth.user?.active_tenant_id && context.allowed('billing');
    function reset() {
        generation++;
        controller?.abort();
        controller = new AbortController();
        data.value = null; error.value = null; busy.value = false; loading.value = false;
        amount.value = ''; reference.value = ''; method.value = 'cash'; methods.value = [];
        paymentKey = crypto.randomUUID();
    }
    async function load(page = 1) {
        if (!ready()) return;
        const current = ++generation;
        controller?.abort(); controller = new AbortController();
        const config = { headers: context.headers(), signal: controller.signal };
        data.value = null; error.value = null; loading.value = true;
        try {
            const response = await (invoiceId.value ? billingApi.show(invoiceId.value, config) : billingApi.list(page, config, customer() || {}));
            if (current !== generation) return;
            data.value = response.data.data;
            methods.value = response.data.methods;
            method.value = methods.value[0] || 'cash';
            if (invoiceId.value) amount.value = data.value.balance;
        } catch (e) { if (current === generation && e.code !== 'ERR_CANCELED') error.value = e; }
        finally { if (current === generation) loading.value = false; }
    }
    async function pay() {
        if (!ready() || !data.value || busy.value) return;
        const current = generation;
        busy.value = true; error.value = null;
        try {
            await billingApi.payment(invoiceId.value, { amount: amount.value, method: method.value, reference: reference.value, idempotency_key: paymentKey },
                { headers: context.headers(), signal: controller.signal });
            if (current !== generation) return;
            paymentKey = crypto.randomUUID(); reference.value = ''; busy.value = false;
            await load();
        } catch (e) { if (current === generation && e.code !== 'ERR_CANCELED') error.value = e; }
        finally { if (current === generation) busy.value = false; }
    }
    watch(() => [auth.user?.active_tenant_id, context.data?.clinic.id, context.data?.branch?.id, invoiceId.value, customer()?.customer_type, customer()?.customer_id], () => {
        reset();
        const tenant = auth.user?.active_tenant_id;
        const changedTenant = previousTenant && tenant !== previousTenant;
        previousTenant = tenant;
        if (changedTenant && invoiceId.value) { router.replace('/app/billing/invoices'); return; }
        if (ready()) load();
    }, { immediate: true, flush: 'sync' });
    onUnmounted(reset);
    return { context, data, error, busy, loading, amount, method, reference, methods, invoiceId, load, pay };
}
