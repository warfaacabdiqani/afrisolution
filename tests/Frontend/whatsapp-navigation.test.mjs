import test from 'node:test';
import assert from 'node:assert/strict';
import { accessDenial } from '../../resources/js/config/businessAccess.js';

const context = permissions => ({
    operational: true,
    modules: [{ key: 'settings', business_allowed: true, business_modules: ['settings'], feature: null, permission: 'clinic_settings.view' }],
    business_modules: { settings: true },
    features: { whatsapp_notifications: true },
    permissions,
});
const whatsapp = { clinicModule: 'settings', whatsapp: true, feature: 'whatsapp_notifications', permission: 'whatsapp.view', businessModule: ['settings'] };

test('WhatsApp operations use their own permission without granting access to settings', () => {
    assert.equal(accessDenial(context(['whatsapp.view']), whatsapp), null);
    assert.equal(accessDenial(context(['whatsapp.view']), { clinicModule: 'settings', businessModule: ['settings'] }), 'PERMISSION_DENIED');
    assert.equal(accessDenial(context(['clinic_settings.view']), whatsapp), 'PERMISSION_DENIED');
});

test('WhatsApp operations still require the plan feature and business capability', () => {
    const noFeature = context(['whatsapp.view']);
    noFeature.features.whatsapp_notifications = false;
    assert.equal(accessDenial(noFeature, whatsapp), 'PLAN_FEATURE_UNAVAILABLE');
    const noCapability = context(['whatsapp.view']);
    noCapability.business_modules.settings = false;
    assert.equal(accessDenial(noCapability, whatsapp), 'BUSINESS_MODULE_UNAVAILABLE');
});
