<script setup>
import { onMounted, ref } from 'vue';
import api from '../../../services/api';
import FormErrors from '../../../components/ui/FormErrors.vue';

const loading = ref(true);
const error = ref(null);
const rows = ref([]);

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const response = await api.get('/v1/platform/business-types');
        rows.value = response.data.data;
    } catch (e) {
        error.value = e;
    } finally {
        loading.value = false;
    }
}

async function toggleStatus(row) {
    const action = row.status === 'active' ? 'deactivate' : 'activate';
    try {
        await api.post(`/v1/platform/business-types/${row.id}/${action}`);
        await load();
    } catch (e) {
        error.value = e;
    }
}

onMounted(load);
</script>

<template>
    <div>
        <header class="page-header">
            <div>
                <p class="eyebrow">Platform administration / Business Types</p>
                <h1>Business Types</h1>
                <p>Configure the industries supported by the platform.</p>
            </div>
        </header>

        <FormErrors :error="error" />

        <section v-if="loading" class="loading-state">Loading business types…</section>

        <section v-else class="admin-card">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Summary</p>
                    <p class="text-2xl font-bold">{{ rows.length }} total types</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Business Type</th>
                            <th>Category</th>
                            <th>Slug</th>
                            <th>Tenants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td>
                                <div class="font-semibold">{{ row.name }}</div>
                            </td>
                            <td>{{ row.category }}</td>
                            <td>{{ row.slug }}</td>
                            <td>{{ row.tenant_count }}</td>
                            <td>
                                <span :class="['badge', row.status === 'active' ? 'badge-success' : 'badge-muted']">
                                    {{ row.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn-secondary" @click="toggleStatus(row)">
                                    {{ row.status === 'active' ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
