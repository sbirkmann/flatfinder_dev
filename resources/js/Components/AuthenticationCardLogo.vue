<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link :href="'/'">
        <svg viewBox="0 0 550 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-64 block" height="100%">
            <path d="M 20 50 L 50 20 L 80 50 L 80 80 L 20 80 Z" fill="#F68F1F"/>
            <path d="M 40 40 L 50 30 L 60 40 L 60 80 L 40 80 Z" fill="#111827"/>
            <text x="95" y="52" font-family="'Inter', sans-serif" font-weight="900" font-size="75" letter-spacing="-3" dominant-baseline="central">
            <tspan fill="#111827">FLAT</tspan>
            <tspan fill="#F68F1F">PLAN</tspan>
            </text>
        </svg>
    </Link>
</template>
