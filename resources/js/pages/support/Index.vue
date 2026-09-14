<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AppIcon from '../../components/ui/AppIcon.vue';
import FormErrors from '../../components/ui/FormErrors.vue';
import { supportService } from '../../services/support';
import { useClinicContextStore } from '../../stores/clinicContext';
import { ticketStatus } from '../../config/supportTickets';

const context = useClinicContextStore();
const articles = ref([]);
const faqs = ref([]);
const tickets = ref([]);
const systemInfo = ref(null);
const selectedArticle = ref(null);
const search = ref('');
const error = ref(null);
const success = ref('');
const expandedFaq = ref(null);
const activeTab = ref('articles');

const ticketForm = reactive({
    subject: '',
    category: 'Technical Issue',
    priority: 'Normal',
    description: '',
    current_page: '',
    steps_to_reproduce: '',
    expected_result: '',
    actual_result: '',
    attachment: null,
});

const filteredArticles = computed(() => {
    if (!search.value.trim()) return articles.value;
    const term = search.value.trim().toLowerCase();

    return articles.value.filter(article => {
        const haystack = [
            article.title,
            article.category,
            article.summary,
            ...(article.keywords || []),
            ...(article.content || []),
        ].join(' ').toLowerCase();

        return haystack.includes(term);
    });
});

function resetTicketForm() {
    ticketForm.subject = '';
    ticketForm.category = 'Technical Issue';
    ticketForm.priority = 'Normal';
    ticketForm.description = '';
    ticketForm.current_page = '';
    ticketForm.steps_to_reproduce = '';
    ticketForm.expected_result = '';
    ticketForm.actual_result = '';
    ticketForm.attachment = null;
}

async function loadArticles() {
    try {
        const { data } = await supportService.listArticles();
        articles.value = data.data || [];
        if (!selectedArticle.value && articles.value.length) {
            selectedArticle.value = articles.value[0];
        }
    } catch (e) {
        error.value = e;
    }
}

async function loadFaqs() {
    try {
        const { data } = await supportService.listFaqs();
        faqs.value = data.data || [];
    } catch (e) {
        error.value = e;
    }
}

async function loadTickets() {
    try {
        const { data } = await supportService.listTickets();
        tickets.value = data.data || [];
    } catch (e) {
        error.value = e;
    }
}

async function loadSystemInfo() {
    try {
        const { data } = await supportService.getSystemInfo();
        systemInfo.value = data.data || null;
    } catch (e) {
        error.value = e;
    }
}


function toggleFaq(slug) {
    expandedFaq.value = expandedFaq.value === slug ? null : slug;
}

onMounted(async () => {
    await Promise.all([
        loadArticles(),
        loadFaqs(),
        loadTickets(),
        loadSystemInfo(),
    ]);
});
</script>

<template>
    <div class="space-y-6">
        <div class="patient-page-header">
            <div>
                <p class="patient-breadcrumb">
                    <RouterLink to="/app/dashboard">Dashboard</RouterLink>
                    <span>/</span>
                    Help &amp; Support
                </p>
                <h1>Help &amp; Support</h1>
                <p>Search help articles, review common questions, and manage support tickets for {{ context.businessType?.name || 'your business' }}.</p>
            </div>
            <RouterLink to="/app/support/tickets/create" class="btn" type="button">+ New Support Ticket</RouterLink>
        </div>

        <div v-if="context.data?.modules" class="clinic-kpis">
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon members"><AppIcon name="activity" :size="28" /></span>
                <div>
                    <h2>Business</h2>
                    <strong>{{ context.data.clinic?.name || 'Current clinic' }}</strong>
                    <p>{{ context.data.branch?.name || 'Active branch' }}</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon blue"><AppIcon name="calendar" :size="28" /></span>
                <div>
                    <h2>Open tickets</h2>
                    <strong>{{ tickets.filter(ticket => ![ticketStatus.resolved, ticketStatus.closed].includes(ticket.status)).length }}</strong>
                    <p>Awaiting update</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon patient"><AppIcon name="roles" :size="28" /></span>
                <div>
                    <h2>Role</h2>
                    <strong>{{ context.data.role || 'Member' }}</strong>
                    <p>Access level</p>
                </div>
            </section>
            <section class="clinic-panel clinic-kpi">
                <span class="clinic-kpi-icon patient-rose"><AppIcon name="settings" :size="28" /></span>
                <div>
                    <h2>Plan</h2>
                    <strong>{{ systemInfo?.subscription_plan || context.data?.plan?.name || 'Active' }}</strong>
                    <p>Current subscription</p>
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.5fr,1fr]">
            <section class="space-y-6">
                <div class="clinic-panel p-4">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex gap-2">
                            <button class="btn-secondary" type="button" :class="{ 'btn': activeTab === 'articles' }" @click="activeTab = 'articles'">Articles</button>
                            <button class="btn-secondary" type="button" :class="{ 'btn': activeTab === 'tickets' }" @click="activeTab = 'tickets'">Tickets</button>
                        </div>
                        <label v-if="activeTab === 'articles'" class="field w-full max-w-md">
                            <span class="sr-only">Search help articles</span>
                            <input v-model="search" type="search" placeholder="Search help articles..." />
                        </label>
                        <label v-else class="field w-full max-w-md">
                            <span class="sr-only">Search support tickets</span>
                            <input v-model="search" type="search" placeholder="Search support tickets..." />
                        </label>
                    </div>

                    <div v-if="activeTab === 'articles'" class="space-y-5">
                        <div v-if="filteredArticles.length" class="grid gap-4 md:grid-cols-2">
                            <button
                                v-for="article in filteredArticles"
                                :key="article.slug"
                                type="button"
                                class="clinic-panel p-4 text-left transition hover:border-sky-400"
                                :class="selectedArticle?.slug === article.slug ? 'border-sky-500 bg-sky-50' : ''"
                                @click="selectedArticle = article"
                            >
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-600">{{ article.category }}</span>
                                    <span class="text-xs text-slate-500">Updated {{ article.updated_at }}</span>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-800">{{ article.title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ article.summary }}</p>
                            </button>
                        </div>
                        <div v-else class="clinic-empty">
                            <AppIcon name="activity" :size="36" />
                            <strong>No matching articles</strong>
                            <p class="text-sm text-slate-500">Try a different keyword or browse the FAQs below.</p>
                        </div>

                        <div v-if="selectedArticle" class="clinic-panel p-5">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-sky-700">{{ selectedArticle.category }}</p>
                                    <h2 class="mt-1 text-2xl font-bold text-slate-800">{{ selectedArticle.title }}</h2>
                                </div>
                                <span class="text-xs text-slate-500">Updated {{ selectedArticle.updated_at }}</span>
                            </div>
                            <div class="space-y-4 text-sm leading-7 text-slate-700">
                                <p v-for="(paragraph, index) in selectedArticle.content || []" :key="index">
                                    {{ paragraph }}
                                </p>
                            </div>

                            <div v-if="selectedArticle.related?.length" class="mt-5 border-t border-slate-200 pt-4">
                                <h3 class="mb-3 text-sm font-semibold uppercase tracking-[0.08em] text-slate-600">Related articles</h3>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="related in selectedArticle.related"
                                        :key="related"
                                        type="button"
                                        class="btn-secondary"
                                        @click="selectedArticle = articles.find(article => article.slug === related) || selectedArticle"
                                    >
                                        {{ articles.find(article => article.slug === related)?.title || related }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-if="tickets.length" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <table class="min-w-full text-left">
                                <thead class="bg-slate-50 text-xs uppercase tracking-[0.08em] text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Ticket</th>
                                        <th class="px-4 py-3">Subject</th>
                                        <th class="px-4 py-3">Category</th>
                                        <th class="px-4 py-3">Priority</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Created</th>
                                        <th class="px-4 py-3">Updated</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="ticket in tickets" :key="ticket.id" class="border-t border-slate-200 text-sm text-slate-600">
                                        <td class="px-4 py-3 font-semibold text-slate-700">{{ ticket.ticket_number }}</td>
                                        <td class="px-4 py-3">{{ ticket.subject }}</td>
                                        <td class="px-4 py-3">{{ ticket.category }}</td>
                                        <td class="px-4 py-3">{{ ticket.priority }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium uppercase text-slate-700">{{ ticket.status }}</span>
                                        </td>
                                        <td class="px-4 py-3">{{ ticket.created_at }}</td>
                                        <td class="px-4 py-3">{{ ticket.updated_at }}</td>
                                        <td class="px-4 py-3">
                                            <RouterLink :to="`/app/support/tickets/${ticket.id}`" class="btn-secondary inline-flex">View</RouterLink>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="clinic-empty">
                            <AppIcon name="members" :size="36" />
                            <strong>No support tickets yet</strong>
                            <p class="text-sm text-slate-500">Create a ticket and the support team will respond here.</p>
                            <RouterLink to="/app/support/tickets/create" class="btn">+ New Support Ticket</RouterLink>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="clinic-panel p-4">
                    <h2 class="mb-3 text-lg font-semibold text-slate-800">Frequently asked questions</h2>
                    <div class="space-y-2">
                        <div v-for="faq in faqs" :key="faq.question" class="rounded-lg border border-slate-200 bg-slate-50">
                            <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-medium text-slate-700" @click="toggleFaq(faq.question)">
                                {{ faq.question }}
                                <AppIcon :name="expandedFaq === faq.question ? 'chevron-up' : 'chevron-down'" :size="16" />
                            </button>
                            <p v-if="expandedFaq === faq.question" class="border-t border-slate-200 px-3 py-2 text-sm leading-6 text-slate-600">
                                {{ faq.answer }}
                            </p>
                        </div>
                    </div>
                </section>

                <section v-if="systemInfo" class="clinic-panel p-4">
                    <h2 class="mb-3 text-lg font-semibold text-slate-800">System information</h2>
                    <dl class="space-y-2 text-sm text-slate-600">
                        <div class="flex justify-between gap-3"><dt>Environment</dt><dd>{{ systemInfo.environment }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Clinic</dt><dd>{{ systemInfo.clinic }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Branch</dt><dd>{{ systemInfo.branch }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Role</dt><dd>{{ systemInfo.user_role }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Version</dt><dd>{{ systemInfo.afri_clinic_version }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>

    </div>
</template>
