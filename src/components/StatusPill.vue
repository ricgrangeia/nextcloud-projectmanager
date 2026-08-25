<script setup>
import { computed } from 'vue'
import { statusLabel } from '../utils/statusLabels.js'

const props = defineProps({
	status: { type: String, required: true },
})

const label = computed(() => statusLabel(props.status))
</script>

<template>
	<span class="status-pill" :class="`status-${status}`">{{ label }}</span>
</template>

<style scoped>
.status-pill {
	display: inline-block;
	padding: 2px 10px;
	border-radius: var(--border-radius-pill, 16px);
	font-size: 12px;
	font-weight: 600;
	white-space: nowrap;
	border: 1.5px solid transparent;
}

/* Neutral / not started yet — uses the theme's own surface + muted text. */
.status-todo,
.status-not_started,
.status-to_test {
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}

/* Active work — Nextcloud's semantic "warning" tone, theme-aware since NC 32. */
.status-in_progress {
	background-color: var(--color-warning);
	color: var(--color-text-warning);
}

/* In-between state: outlined rather than filled, so it reads as distinct
   from a fully "in progress" pill without inventing a new hue. */
.status-partial {
	background-color: transparent;
	border-color: var(--color-warning);
	color: var(--color-main-text);
}

.status-done,
.status-passed {
	background-color: var(--color-success);
	color: var(--color-text-success);
}

.status-failed {
	background-color: var(--color-error);
	color: var(--color-text-error);
}
</style>
