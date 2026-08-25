<script setup>
import { ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import HelpCircleOutline from 'vue-material-design-icons/HelpCircleOutline.vue'

defineProps({
	title: { type: String, required: true },
})

const open = ref(false)
</script>

<template>
	<button type="button" class="help-btn" :aria-label="title" :title="title" @click="open = true">
		<HelpCircleOutline :size="18" />
	</button>
	<NcDialog :open="open" :name="title" size="normal" @update:open="(v) => (open = v)">
		<div class="help-content">
			<slot />
		</div>
	</NcDialog>
</template>

<style scoped>
.help-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	background: none;
	border: none;
	border-radius: 50%;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	vertical-align: middle;
	flex-shrink: 0;
}

.help-btn:hover {
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
}

.help-content {
	padding: 4px 0 16px;
	line-height: 1.6;
}

.help-content :deep(h4) {
	margin: 20px 0 6px;
	font-size: 14px;
}

.help-content :deep(h4:first-child) {
	margin-top: 0;
}

.help-content :deep(p) {
	margin: 0 0 12px;
	color: var(--color-text-maxcontrast);
}

.help-content :deep(p:last-child) {
	margin-bottom: 0;
}
</style>
