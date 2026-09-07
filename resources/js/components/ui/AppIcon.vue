<script setup>
defineProps({name:String,size:{type:Number,default:20}});
const paths={
plans:'M4 7c0 1.7 3.6 3 8 3s8-1.3 8-3-3.6-3-8-3-8 1.3-8 3Zm0 0v5c0 1.7 3.6 3 8 3s8-1.3 8-3V7M4 12v5c0 1.7 3.6 3 8 3s8-1.3 8-3v-5',
check:'m5 12 4 4L19 6',
subscriptions:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
trial:'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-16v6l4 2',
branch:'M3 21h18M5 21V7l7-4v18M19 21V11l-7-4M8 9h1M8 13h1M8 17h1M15 13h1M15 17h1',
members:'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87',
doctor:'M9 3H5a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-4Zm6 6a4 4 0 0 1 8 0v4a4 4 0 0 1-8 0',
patient:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0M19 8v6M16 11h6',
storage:'M4 6c0 1.7 3.6 3 8 3s8-1.3 8-3-3.6-3-8-3-8 1.3-8 3Zm0 0v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6m-8 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6',
view:'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
edit:'M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z',
pause:'M10 9v6M14 9v6M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
trash:'M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15M10 11v6M14 11v6',
};
</script>
<template><svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name]||paths.check"/></svg></template>
