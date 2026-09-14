// Capability mappings come from clinic.modules in tenant context.
export function accessDenial(data, meta) {
    if (!data) return null;
    if (!data.operational) return data.restriction_code || 'SUBSCRIPTION_INACTIVE';
    const entry = data.modules.find(module => module.key === meta.clinicModule);
    if (meta.clinicModule && (!entry || !entry.business_allowed)) return 'BUSINESS_MODULE_UNAVAILABLE';
    const capabilities = [meta.businessModule].flat().filter(Boolean);
    if (capabilities.some(key => data.business_modules[key] !== true)) return 'BUSINESS_MODULE_UNAVAILABLE';
    if ([entry?.feature, meta.planFeature, meta.feature].some(key => key && !data.features[key])) return 'PLAN_FEATURE_UNAVAILABLE';
    if ([entry?.permission, meta.permission].some(key => key && !data.permissions.some(p => p === '*' || p === key))) return 'PERMISSION_DENIED';
    return null;
}
