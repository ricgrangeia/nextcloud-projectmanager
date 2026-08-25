<script setup>
import { t } from '@nextcloud/l10n'
import api from '../api/client.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})
</script>

<template>
	<div class="project-view">
		<nav class="project-tabs">
			<router-link :to="{ name: 'grid', params: { id } }">{{ t('projectmanager', 'Hours grid') }}</router-link>
			<router-link :to="{ name: 'features', params: { id } }">{{ t('projectmanager', 'Features') }}</router-link>
			<router-link :to="{ name: 'tests', params: { id } }">{{ t('projectmanager', 'Tests') }}</router-link>
			<a class="export-link" :href="api.exportUrl(id)">{{ t('projectmanager', 'Export .xlsx') }}</a>
		</nav>
		<router-view :id="id" />
	</div>
</template>

<style scoped>
.project-view {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.project-tabs {
	display: flex;
	justify-content: flex-end;
	gap: 4px;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border);
	flex-shrink: 0;
}

.project-tabs a {
	padding: 8px 12px;
	border-radius: var(--border-radius-large);
	color: var(--color-text-maxcontrast);
	text-decoration: none;
}

.project-tabs a:hover {
	background-color: var(--color-background-hover);
}

.project-tabs a.router-link-exact-active {
	color: var(--color-main-text);
	background-color: var(--color-primary-element-light);
	font-weight: bold;
}

.export-link {
	padding: 8px 12px;
}
</style>
