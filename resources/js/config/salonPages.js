const field = (key,label,type='text',extra={}) => ({key,label,type,...extra});
export const salonPages = {
    clients: { title:'Clients', singular:'Client', description:'Manage salon clients and service history.', endpoint:'clients', create:'clients.create', update:'clients.update', archive:'clients.archive',
        columns:[['full_name','Client'],['client_number','Client ID'],['phone','Phone'],['preferred_stylist','Preferred Stylist'],['last_visit','Last Visit'],['status','Status']],
        fields:[field('first_name','First Name','text',{required:true,max:100,group:'Personal Information'}),field('middle_name','Middle Name'),field('last_name','Last Name','text',{required:true,max:100}),field('gender','Gender','select',{values:['female','male','other','prefer_not_to_say']}),field('date_of_birth','Date of Birth','date'),field('phone','Phone','tel',{group:'Contact Information'}),field('email','Email','email'),field('address','Address','textarea'),field('preferred_stylist_id','Preferred Stylist','select',{source:'stylists',optionLabel:'display_name',numeric:true,group:'Preferences'}),field('notes','Notes','textarea'),field('status','Status','select',{values:['active','inactive'],required:true})],
        defaults:{first_name:'',middle_name:'',last_name:'',gender:'',date_of_birth:'',phone:'',email:'',address:'',notes:'',preferred_stylist_id:'',status:'active'},
    },
    stylists: {title:'Stylists',singular:'Stylist',description:'Manage service professionals linked to your existing staff accounts.',endpoint:'stylists',create:'salon_staff.manage',update:'salon_staff.manage',
        columns:[['display_name','Name'],['staff_number','Staff ID'],['title','Title'],['branches','Location'],['services','Services'],['status','Status']],
        fields:[field('user_id','Staff Account','select',{source:'users',optionLabel:'name',numeric:true,required:true,immutable:true}),field('display_name','Display Name','text',{required:true,max:150}),field('title','Title','select',{source:'titles'}),field('bio','Biography','textarea'),field('branch_ids','Locations','multi',{source:'branches',optionLabel:'name'}),field('commission_type','Commission Type','select',{values:['percentage','fixed']}),field('commission_value','Commission Value','number',{min:0,step:'0.01'}),field('status','Status','select',{values:['active','inactive'],required:true})],
        defaults:{user_id:'',display_name:'',title:'',bio:'',branch_ids:[],commission_type:'',commission_value:'',status:'active'},
    },
    services: {title:'Services',singular:'Service',description:'Manage salon services, pricing, duration and stylist assignments.',endpoint:'services',create:'services.create',update:'services.update',archive:'services.archive',
        columns:[['name','Service'],['category','Category'],['duration_minutes','Duration'],['price','Price'],['stylists','Assigned Staff'],['status','Status']],
        fields:[field('name','Service Name','text',{required:true,max:150}),field('code','Service Code','text',{max:40}),field('service_category_id','Category','select',{source:'categories',optionLabel:'name',numeric:true,required:true}),field('description','Description','textarea'),field('duration_minutes','Duration (minutes)','number',{required:true,min:5,max:1440}),field('price','Price','number',{required:true,min:0,step:'0.01'}),field('requires_deposit','Requires Deposit','checkbox'),field('deposit_amount','Deposit Amount','number',{min:0,step:'0.01'}),field('branch_ids','Locations','multi',{source:'branches',optionLabel:'name'}),field('stylist_ids','Assigned Stylists','multi',{source:'stylists',optionLabel:'display_name'}),field('status','Status','select',{values:['active','inactive'],required:true})],
        defaults:{name:'',code:'',service_category_id:'',description:'',duration_minutes:30,price:0,requires_deposit:false,deposit_amount:'',branch_ids:[],stylist_ids:[],status:'active'},
    },
    categories: {title:'Service Categories',singular:'Category',description:'Organize services using your own salon categories.',path:'services/categories',endpoint:'service-categories',create:'service_categories.manage',update:'service_categories.manage',
        columns:[['name','Category'],['description','Description'],['sort_order','Sort Order'],['status','Status']],
        fields:[field('name','Category Name','text',{required:true,max:100}),field('description','Description','textarea'),field('sort_order','Sort Order','number',{min:0,max:100000}),field('status','Status','select',{values:['active','inactive'],required:true})],defaults:{name:'',description:'',sort_order:0,status:'active'},
    },
};
export const salonPath = kind => '/app/' + (salonPages[kind].path || kind);
export function salonValue(value,key,currency='USD') {
    if (key==='last_visit') return 'Not available yet';
    if (value === null || value === undefined || value === '') return '—';
    if (Array.isArray(value)) return value.map(row=>row.display_name || row.name).join(', ') || '—';
    if (typeof value==='object') return value.display_name || value.name || '—';
    if (key==='price') return new Intl.NumberFormat(undefined,{style:'currency',currency}).format(value);
    if (key==='duration_minutes') return value+' min';
    if (typeof value==='boolean') return value?'Yes':'No';
    return value;
}
