<script setup>
defineProps({name:String,size:{type:Number,default:20}});
const paths={
support:'M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z',
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
clinics:'M3 21h18M5 21V5h10v16M9 9h2M9 13h2M9 17h2M15 10h4v11M17 14h2M17 18h2',
revenue:'M12 2v20m5-16.5C15.8 4.5 14.2 4 12 4c-3 0-5 1.3-5 3s2 3 5 3 5 1.3 5 3-2 3-5 3c-2.2 0-3.8-.5-5-1.5',
activity:'M3 12h4l3-8 4 16 3-8h4',
calendar:'M3 5h18v16H3V5Zm4-3v6m10-6v6M3 10h18',
dashboard:'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z',
roles:'M12 3 4 6v6c0 4.5 3.2 7.4 8 9 4.8-1.6 8-4.5 8-9V6l-8-3Zm-3 9 2 2 4-4',
audit:'M9 3h6l1 2h3v16H5V5h3l1-2Zm0 7h6m-6 4h6m-6 4h4',
settings:'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.4-3.5a7 7 0 0 0-.1-1l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3.5h-4L10.6 6a8 8 0 0 0-1.7 1l-2.4-1-2 3.4L6.6 11a7 7 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.4 2.6h4l.4-2.6a8 8 0 0 0 1.7-1l2.4 1 2-3.4-2.1-1.5a7 7 0 0 0 .1-1Z',
menu:'M4 6h16M4 12h16M4 18h16',
chevronLeft:'m15 18-6-6 6-6',
chevronRight:'m9 18 6-6-6-6',
};
</script>
<template><svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name]||paths.check"/></svg></template>
