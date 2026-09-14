<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../../services/api';
import FormErrors from '../../../components/ui/FormErrors.vue';

function generateSlug(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 80);
}

const router = useRouter();
const plans = ref([]);
const businessTypes = ref([]);
const busy = ref(false);
const error = ref(null);

const form = reactive({
    name: '',
    slug: '',
    timezone: 'Africa/Nairobi',
    owner_name: '',
    owner_email: '',
    owner_password: '',
    owner_password_confirmation: '',
    plan_id: '',
    business_type_id: '',
    location_name: '',
});

const activeBusinessTypes = computed(() =>
    businessTypes.value.filter((type) => type.status === 'active')
);

watch(
    () => form.name,
    (value) => {
        if (!value || form.slug.trim()) return;
        form.slug = generateSlug(value);
    },
    { immediate: true }
);

const selectedBusinessType = computed(() => {
    const selectedId = Number(form.business_type_id);

    return activeBusinessTypes.value.find((type) => Number(type.id) === selectedId) || null;
});

const businessProfile = computed(() => {
    const slug = selectedBusinessType.value?.slug ?? 'clinic';

    const profiles = {
        clinic: {
            sectionTitle: 'Clinic Information',
            nameLabel: 'Clinic Name',
            codeLabel: 'Clinic Code',
            createLabel: 'Create Clinic',
            locationLabel: 'Initial Branch',
            locationHint: 'Main branch will be created automatically.',
            locationName: 'Main Branch',
        },
        dental: {
            sectionTitle: 'Dental Clinic Information',
            nameLabel: 'Dental Clinic Name',
            codeLabel: 'Dental Clinic Code',
            createLabel: 'Create Dental Clinic',
            locationLabel: 'Initial Branch',
            locationHint: 'Main branch will be created automatically.',
            locationName: 'Main Branch',
        },
        'beauty-salon': {
            sectionTitle: 'Salon Information',
            nameLabel: 'Salon Name',
            codeLabel: 'Salon Code',
            createLabel: 'Create Salon',
            locationLabel: 'Initial Location',
            locationHint: 'Main location will be created automatically.',
            locationName: 'Main Location',
        },
        stadium: {
            sectionTitle: 'Stadium Information',
            nameLabel: 'Stadium Name',
            codeLabel: 'Stadium Code',
            createLabel: 'Create Stadium',
            locationLabel: 'Initial Location',
            locationHint: 'Main location will be created automatically.',
            locationName: 'Main Location',
        },
    };

    return profiles[slug] ?? {
        sectionTitle: 'Business Information',
        nameLabel: 'Business Name',
        codeLabel: 'Business Code',
        createLabel: 'Create Business',
        locationLabel: 'Initial Location',
        locationHint: 'Main location will be created automatically.',
        locationName: 'Main Location',
    };
});

function defaultLocationName(slug) {
    if (slug === 'beauty-salon' || slug === 'stadium') {
        return 'Main Location';
    }

    return 'Main Branch';
}

function applyBusinessTypeDefaults(typeId = '') {
    const selected = activeBusinessTypes.value.find((item) => Number(item.id) === Number(typeId));

    form.location_name = selected ? defaultLocationName(selected.slug) : defaultLocationName('clinic');
}

onMounted(async () => {
    try {
        const [planResponse, businessTypeResponse] = await Promise.all([
            api.get('/v1/platform/plans'),
            api.get('/v1/platform/business-types'),
        ]);

        plans.value = planResponse.data.data;
        businessTypes.value = businessTypeResponse.data.data.filter((type) => type.status === 'active');

        const defaultBusinessType = businessTypes.value.find((item) => item.slug === 'clinic')
            || businessTypes.value[0]
            || null;

        if (defaultBusinessType) {
            form.business_type_id = String(defaultBusinessType.id);
            form.location_name = defaultLocationName(defaultBusinessType.slug);
        }
    } catch (e) {
        error.value = e;
    }
});

async function submit() {
    busy.value = true;
    error.value = null;

    try {
        if (!form.slug.trim()) {
            form.slug = generateSlug(form.name);
        }

        const id = (await api.post('/v1/platform/tenants', form)).data.data.id;

        await router.replace({
            name: 'admin.clinics.show',
            params: { id },
            query: { created: '1' },
        });
    } catch (e) {
        error.value = e;
    } finally {
        form.owner_password = '';
        form.owner_password_confirmation = '';
        busy.value = false;
    }
}
</script>

<template>
    <div>
        <header class="page-header">
            <div>
                <RouterLink class="back-link" :to="{ name: 'admin.clinics' }">← Back to Businesses</RouterLink>
                <h1 class="mt-3">Add New Business</h1>
                <p>Create a new business organization and its owner workspace.</p>
            </div>
        </header>

        <FormErrors :error="error" />

        <form class="space-y-6" @submit.prevent="submit">
            <section class="admin-card">
                <h2 class="form-section-title">1. Business Type</h2>
                <div class="form-grid">
                    <label class="field">
                        Business Type
                        <select v-model="form.business_type_id" required @change="applyBusinessTypeDefaults(form.business_type_id)">
                            <option disabled value="">Select a business type</option>
                            <option v-for="item in activeBusinessTypes" :key="item.id" :value="item.id">
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <div v-if="selectedBusinessType" class="mt-4 rounded border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                    {{ selectedBusinessType.description || 'Business profile ready for creation.' }}
                </div>
            </section>

            <section class="admin-card">
                <h2 class="form-section-title">2. {{ businessProfile.sectionTitle }}</h2>
                <div class="form-grid">
                    <label class="field">
                        {{ businessProfile.nameLabel }}
                        <input v-model="form.name" required maxlength="150" />
                    </label>
                    <label class="field">
                        {{ businessProfile.codeLabel }}
                        <input v-model="form.slug" required pattern="[A-Za-z0-9_-]+" maxlength="80" />
                        <span v-if="form.name" class="hint">Auto-generated from the business name. You can edit the code before creating.</span>
                    </label>
                    <label class="field">
                        Timezone
                        <input v-model="form.timezone" required />
                    </label>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="form-section-title">3. Owner Account</h2>
                <div class="form-grid">
                    <label class="field">
                        Owner name
                        <input v-model="form.owner_name" required />
                    </label>
                    <label class="field">
                        Owner email
                        <input v-model="form.owner_email" type="email" required />
                    </label>
                    <label class="field">
                        Password
                        <input v-model="form.owner_password" type="password" minlength="12" autocomplete="new-password" required />
                        <span class="hint">12+ characters with mixed case and a number.</span>
                    </label>
                    <label class="field">
                        Confirm password
                        <input v-model="form.owner_password_confirmation" type="password" autocomplete="new-password" required />
                    </label>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="form-section-title">4. Subscription</h2>
                <div class="form-grid">
                    <label class="field">
                        Plan
                        <select v-model="form.plan_id" required>
                            <option disabled value="">Select a plan</option>
                            <option v-for="item in plans" :key="item.id" :value="item.id">
                                {{ item.name }} · {{ item.trial_days }} trial days
                            </option>
                        </select>
                    </label>
                    <div>
                        <p class="field">Initial status</p>
                        <p class="mt-2 text-sm text-slate-600">Trial — set from the selected plan.</p>
                    </div>
                </div>
            </section>

            <section class="admin-card">
                <h2 class="form-section-title">5. {{ businessProfile.locationLabel }}</h2>
                <p class="text-sm text-slate-600 mb-4">{{ businessProfile.locationHint }}</p>
                <div class="form-grid">
                    <label class="field">
                        Location Name
                        <input v-model="form.location_name" maxlength="150" />
                    </label>
                </div>
            </section>

            <div class="flex justify-end gap-3 pt-2 pb-8">
                <RouterLink class="btn-secondary" :to="{ name: 'admin.clinics' }">Cancel</RouterLink>
                <button class="btn" type="submit" :disabled="busy">
                    {{ busy ? 'Creating...' : businessProfile.createLabel }}
                </button>
            </div>
        </form>
    </div>
</template>
