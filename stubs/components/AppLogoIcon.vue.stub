<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 100 100"
        :class="className"
        v-bind="$attrs"
    >
        <title>Laravilt</title>
        <rect x="4" y="22.4" width="18.4" height="18.4" fill="#FF2D20"/>
        <rect x="22.4" y="40.8" width="18.4" height="18.4" fill="#FF2D20"/>
        <rect x="4" y="59.2" width="18.4" height="18.4" fill="#FF2D20"/>
        <rect x="40.8" y="22.4" width="18.4" height="18.4" fill="#9553E9"/>
        <rect x="40.8" y="59.2" width="18.4" height="18.4" fill="#9553E9"/>
        <rect x="59.2" y="40.8" width="18.4" height="18.4" fill="#9553E9"/>
        <rect x="77.6" y="22.4" width="18.4" height="18.4" fill="#9553E9"/>
    </svg>
</template>
