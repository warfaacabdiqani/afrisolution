<script setup>
import { computed, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FormErrors from '../../components/ui/FormErrors.vue';
import { useAuthStore } from '../../stores/auth';
import { useClinicContextStore } from '../../stores/clinicContext';
import { supportService } from '../../services/support';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const context = useClinicContextStore();

const error = ref(null);
const submitting = ref(false);
const technicalInfoOpen = ref(false);

const form = reactive({
    subject: '',
    category: 'Technical Issue',
    priority: 'Normal',
    description: '',
    current_page: route.fullPath || '/app/support',
    steps_to_reproduce: '',
    expected_result: '',
    actual_result: '',
    attachment: null,
});

const showTroubleshootingFields = computed(() => {
    return ['Technical Issue', 'Bug Report'].includes(form.category);
});

const safeContext = computed(() => ({
    clinic: context.data?.clinic?.name || 'Current clinic',
    branch: context.data?.branch?.name || 'Current branch',
    user: auth.user?.name || 'Current user',
    role: context.data?.role || 'Clinic role',
    current_route: route.fullPath || '/app/support',
    app_version: '1.0.0',
    browser: navigator.userAgent || 'Unknown browser',
}));

function clearForm() {
    form.subject = '';
    form.category = 'Technical Issue';
    form.priority = 'Normal';
    form.description = '';
    form.current_page = route.fullPath || '/app/support';
    form.steps_to_reproduce = '';
    form.expected_result = '';
    form.actual_result = '';
    form.attachment = null;
}

async function submit() {
    submitting.value = true;
    error.value = null;

    try {
        const payload = new FormData();
        payload.append('subject', form.subject);
        payload.append('category', form.category);
        payload.append('priority', form.priority);
        payload.append('description', form.description);
        payload.append('current_page', form.current_page || route.fullPath || '/app/support');
        payload.append('steps_to_reproduce', form.steps_to_reproduce || '');
        payload.append('expected_result', form.expected_result || '');
        payload.append('actual_result', form.actual_result || '');

        if (form.attachment) {
            payload.append('attachment', form.attachment);
        }

        Object.entries(safeContext.value).forEach(([key, value]) => {
            if (value) {
                payload.append(key, String(value));
            }
        });

        const { data } = await supportService.createTicket(payload);
        const ticketId = data.data?.ticket_id || data.ticket_id;

        router.push({
            path: `/app/support/tickets/${ticketId}`,
            query: { created: '1' },
        });
    } catch (err) {
        error.value = err;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6 py-4">
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/support">Help &amp; Support</RouterLink>
                    <span>/</span>
                    New Support Ticket
                </p>
                <h1>New Support Ticket</h1>
                <p>Describe the issue and provide enough information for the support team to help you.</p>
            </div>
        </div>

        <section class="clinic-panel p-6">
            <FormErrors :error="error" />

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                <label class="field md:col-span-2">
                    <span>Subject *</span>
                    <input v-model="form.subject" type="text" required />
                </label>

                <label class="field">
                    <span>Category *</span>
                    <select v-model="form.category">
                        <option>Technical Issue</option>
                        <option>Account / Access</option>
                        <option>Billing / Subscription</option>
                        <option>Feature Question</option>
                        <option>Bug Report</option>
                        <option>Other</option>
                    </select>
                </label>

                <label class="field">
                    <span>Priority *</span>
                    <select v-model="form.priority">
                        <option>Low</option>
                        <option>Normal</option>
                        <option>High</option>
                        <option>Urgent</option>
                    </select>
                </label>

                <label class="field md:col-span-2">
                    <span>Current Page</span>
                    <input v-model="form.current_page" type="text" />
                </label>

                <label class="field md:col-span-2">
                    <span>Description *</span>
                    <textarea v-model="form.description" rows="5" required></textarea>
                </label>

                <div v-if="showTroubleshootingFields" class="md:col-span-2 grid gap-4 md:grid-cols-2">
                    <label class="field">
                        <span>Steps to Reproduce</span>
                        <textarea v-model="form.steps_to_reproduce" rows="4"></textarea>
                    </label>
                    <label class="field">
                        <span>Expected Result</span>
                        <textarea v-model="form.expected_result" rows="4"></textarea>
                    </label>
                    <label class="field md:col-span-2">
                        <span>Actual Result</span>
                        <textarea v-model="form.actual_result" rows="4"></textarea>
                    </label>
                </div>

                <label class="field md:col-span-2">
                    <span>Optional Attachment</span>
                    <input type="file" @change="event => form.attachment = event.target.files?.[0] || null" />
                    <small v-if="form.attachment" class="mt-2 block text-xs text-slate-500">Selected: {{ form.attachment.name }}</small>
                </label>

                <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <button type="button" class="flex w-full items-center justify-between gap-3 text-left text-sm font-medium text-slate-700" @click="technicalInfoOpen = !technicalInfoOpen">
                        <span>Technical Information</span>
                        <span>{{ technicalInfoOpen ? 'Hide' : 'Show' }}</span>
                    </button>
                    <div v-if="technicalInfoOpen" class="mt-4 space-y-2 text-sm text-slate-600">
                        <p>Some system information will be attached automatically.</p>
                        <dl class="grid gap-2 sm:grid-cols-2">
                            <div v-for="(value, key) in safeContext" :key="key" class="rounded border border-slate-200 bg-white p-2">
                                <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">{{ key.replace('_', ' ') }}</dt>
                                <dd class="mt-1 break-all text-slate-700">{{ value }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="md:col-span-2 flex flex-col-reverse justify-end gap-3 pt-2 sm:flex-row">
                    <RouterLink to="/app/support" class="btn-secondary inline-flex items-center justify-center">Cancel</RouterLink>
                    <button class="btn" type="submit" :disabled="submitting">
                        {{ submitting ? 'Submitting...' : 'Submit Ticket' }}
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
