<script setup>
import { computed, ref } from 'vue';
const props = defineProps({ findings: { type: Array, default: () => [] }, conditions: { type: Object, default: () => ({}) }, modelValue: { type: String, default: '' } });
const emit = defineEmits(['update:modelValue']);
const primary = ref(false);
const arches = computed(() => primary.value
    ? ['ABCDEFGHIJ'.split(''), 'TSRQPONMLK'.split('')]
    : [Array.from({ length: 16 }, (_, i) => String(i + 1)), Array.from({ length: 16 }, (_, i) => String(32 - i))]);
const latest = computed(() => {
    const map = {};
    for (const finding of props.findings) if (!finding.voided_at && !map[finding.tooth]) map[finding.tooth] = finding;
    return map;
});
const label = tooth => props.conditions[latest.value[tooth]?.condition] || 'Uncharted';
</script>

<template>
    <div class="tooth-chart">
        <div class="dentition-tabs" role="group" aria-label="Dentition">
            <button type="button" :aria-pressed="!primary" :class="{ active: !primary }" @click="primary = false">Adult teeth</button>
            <button type="button" :aria-pressed="primary" :class="{ active: primary }" @click="primary = true">Primary teeth</button>
        </div>
        <p class="chart-help">Universal numbering · Patient’s right is on your left. Select a tooth to see its history.</p>
        <div class="chart-scroll" tabindex="0" aria-label="Tooth chart, scroll horizontally on small screens">
            <div class="chart-inner" :class="{ primary }">
                <div class="chart-directions"><span>Patient’s right</span><span>Patient’s left</span></div>
                <div v-for="(arch, index) in arches" :key="index" class="arch" :class="{ lower: index === 1 }" role="group" :aria-label="index ? 'Lower teeth' : 'Upper teeth'">
                    <span class="arch-label">{{ index ? 'Lower' : 'Upper' }}</span>
                    <div class="tooth-row">
                        <button v-for="tooth in arch" :key="tooth" type="button" class="tooth" :class="[latest[tooth]?.condition || 'uncharted', { selected: modelValue === tooth }]"
                            :aria-label="`Tooth ${tooth}, ${label(tooth)}`" :aria-pressed="modelValue === tooth" :title="`Tooth ${tooth}: ${label(tooth)}`" @click="emit('update:modelValue', tooth)">
                            <span class="tooth-number">{{ tooth }}</span>
                            <svg viewBox="0 0 36 46" aria-hidden="true"><path d="M9 4C4 5 3 11 5 19l3 18c1 7 5 7 7 0l3-10 3 10c2 7 6 7 7 0l3-18C33 8 28 1 21 4l-3 1-3-1Z"/><path d="M11 12l7 4 7-4M18 16v7" class="tooth-detail"/></svg>
                            <span class="tooth-marker">{{ latest[tooth] ? (latest[tooth].condition === 'sound' ? '✓' : '●') : '–' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="chart-legend"><span><i class="unmarked"></i>Uncharted</span><span><i class="healthy"></i>Sound</span><span><i class="finding"></i>Finding recorded</span><span><i class="absent"></i>Missing / unerupted</span></div>
    </div>
</template>

<style scoped>
.tooth-chart{min-width:0}.dentition-tabs{display:inline-flex;background:#f1f5f9;padding:4px;border-radius:10px;gap:4px}.dentition-tabs button{padding:8px 14px;border-radius:7px;font-weight:600;font-size:13px}.dentition-tabs .active{background:white;color:#0f766e;box-shadow:0 1px 4px #0001}.chart-help{font-size:13px;color:#64748b;margin:14px 0}.chart-scroll{overflow-x:auto;border:1px solid #e2e8f0;border-radius:14px;background:#fafcfc;padding:14px}.chart-inner{min-width:650px}.chart-inner.primary{min-width:470px;max-width:700px;margin:auto}.chart-directions{display:flex;justify-content:space-between;color:#64748b;font-size:12px;margin-bottom:10px}.arch-label{display:block;text-align:center;color:#64748b;font-size:12px;padding:4px}.tooth-row{display:flex;justify-content:center;gap:3px}.tooth{flex:1;min-width:35px;max-width:57px;display:flex;flex-direction:column;align-items:center;border:2px solid transparent;border-radius:9px;padding:5px 1px;color:#64748b}.tooth:hover{background:#e2e8f0}.tooth:focus-visible{outline:3px solid #0f766e;outline-offset:1px}.tooth.selected{border-color:#0f766e;background:#e6f6f2}.tooth-number{font-size:12px;font-weight:700}.tooth svg{height:42px;width:32px;fill:#fff;stroke:#94a3b8;stroke-width:1.5}.tooth-detail{fill:none}.tooth-marker{font-size:10px}.tooth.sound{color:#15803d}.tooth.sound svg{fill:#dcfce7;stroke:#22a06b}.tooth:not(.uncharted):not(.sound):not(.missing):not(.unerupted){color:#b45309}.tooth:not(.uncharted):not(.sound):not(.missing):not(.unerupted) svg{fill:#fff2cf;stroke:#d49d32}.tooth.missing svg,.tooth.unerupted svg{fill:#e2e8f0;stroke-dasharray:3 2}.lower{border-top:1px dashed #cbd5e1;margin-top:8px;padding-top:7px}.lower svg{transform:rotate(180deg)}.chart-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px;font-size:12px;color:#475569}.chart-legend span{display:flex;gap:5px;align-items:center}.chart-legend i{width:9px;height:9px;border-radius:50%;background:#cbd5e1}.chart-legend .healthy{background:#22a06b}.chart-legend .finding{background:#d49d32}.chart-legend .absent{background:#64748b}
</style>
