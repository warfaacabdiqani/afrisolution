export const auditLabel=key=>key?.split('.').map(word=>word.charAt(0).toUpperCase()+word.slice(1)).join(' ')||'Audit Event';
export const auditTone=key=>key?.includes('deleted')||key?.includes('failed')?'danger':key?.includes('created')?'created':key?.startsWith('login')||key?.startsWith('logout')?'auth':key?.startsWith('subscription')?'subscription':'updated';
export const subjectLabel=type=>({tenant:'Clinic',plan:'Subscription Plan',user:'Administrator',platform_role:'Platform Role'}[type]||type||'Resource');
