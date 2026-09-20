<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true }, businessName: { type: String, required: true },
    branchName: { type: String, default: '' }, period: { type: String, default: '' },
    generatedAt: { type: String, default: '' }, rows: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] }, breakdowns: { type: Array, default: () => [] },
    message: { type: String, default: '' },
});
const columns = computed(() => props.rows.length ? Object.keys(props.rows[0]) : []);
const label = key => key.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
</script>
<template>
    <article class="report-document">
        <header class="report-document-header"><div><strong class="report-business">{{ businessName }}</strong><h2>{{ title }}</h2></div><div class="report-document-context"><p>{{ branchName }}<span v-if="branchName && period"> · </span>{{ period }}</p><p>Generated {{ generatedAt }}</p></div></header>
        <p v-if="summary.length" class="report-summary"><span v-for="(item, index) in summary" :key="`${item.label}-${index}`"><span v-if="index"> · </span><strong>{{ item.value }}</strong> {{ item.label }}</span></p>
        <section v-if="rows.length || !breakdowns.length" class="report-records"><h3>Records</h3><div v-if="rows.length" class="report-table-wrap"><table><thead><tr><th v-for="key in columns" :key="key" scope="col">{{ label(key) }}</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="index"><td v-for="key in columns" :key="key">{{ row[key] ?? '—' }}</td></tr></tbody></table></div><p v-else class="report-empty">No records found for the selected filters.</p></section>
        <section v-for="breakdown in breakdowns" :key="breakdown.title" class="report-breakdown"><h3>{{ breakdown.title }}</h3><div class="report-breakdown-items"><p v-for="item in breakdown.items" :key="item.label">{{ item.label }}: <strong>{{ item.value }}</strong></p></div></section>
        <p v-if="message" class="report-note">{{ message }}</p>
        <footer>{{ title }} · {{ branchName }} · {{ generatedAt }}</footer>
    </article>
</template>
<style scoped>
.report-document{background:#fff;border:1px solid #d7e4ea;border-radius:10px;padding:24px 28px;color:#112b3d;max-width:1200px;margin:auto}.report-document-header{display:flex;align-items:end;justify-content:space-between;gap:18px;border-bottom:2px solid #00665e;padding-bottom:12px}.report-business{font-size:18px}.report-document-header h2{font-size:22px;line-height:1.2;font-weight:700}.report-document-context{text-align:right;font-size:12px;color:#526779}.report-summary{padding:12px 0;font-size:13px;line-height:1.5;border-bottom:1px solid #d7e4ea}.report-records{margin-top:14px}.report-records h3,.report-breakdown h3{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}.report-table-wrap{overflow-x:auto}.report-records table{width:100%;border-collapse:collapse;font-size:12px}.report-records th,.report-records td{padding:7px 9px;border-bottom:1px solid #e1e9ed;text-align:left;vertical-align:top}.report-records th{background:#f1f6f7;color:#40566a;font-weight:700;white-space:nowrap}.report-empty{padding:14px 0;color:#64748b;font-size:13px}.report-breakdown{margin-top:16px;padding-top:10px;border-top:1px solid #d7e4ea}.report-breakdown-items{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px 16px;font-size:12px}.report-note{font-size:12px;color:#526779;margin-top:14px}.report-document footer{font-size:11px;color:#617487;margin-top:18px;padding-top:8px;border-top:1px solid #d7e4ea}@media print{:global(.clinic-sidebar),:global(.clinic-topbar),:global(.clinic-trial),:global(.clinic-overlay),:global(.no-print){display:none!important}:global(.clinic-workspace){margin:0!important;min-height:0!important}:global(.clinic-content){padding:0!important;max-width:none!important}.report-document{border:0;padding:0;max-width:none}.report-document-header,.report-summary{break-after:avoid}.report-table-wrap{overflow:visible}.report-records thead{display:table-header-group}.report-records tr{break-inside:avoid}.report-breakdown{break-inside:avoid}@page{size:A4 portrait;margin:14mm}}
.report-document{width:100%;max-width:none}
@media print {
    :global(html),
    :global(body),
    :global(.clinic-shell),
    :global(.clinic-workspace),
    :global(.clinic-content) {
        background: #fff !important;
        box-shadow: none !important;
    }
}
</style>
