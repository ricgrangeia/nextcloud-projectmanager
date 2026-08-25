<script setup>
import { t } from '@nextcloud/l10n'
import { state, loadProjects } from '../store/index.js'
import api from '../api/client.js'
import { useRouter } from 'vue-router'

const router = useRouter()

async function createExample() {
	const project = await api.createExampleProject()
	await loadProjects()
	router.push({ name: 'grid', params: { id: project.id } })
}
</script>

<template>
	<div class="empty-state">
		<h2>{{ t('projectmanager', 'Project Manager') }}</h2>
		<template v-if="state.loaded && state.projects.length === 0">
			<p>{{ t('projectmanager', 'Create your first project using the "New project" button in the sidebar.') }}</p>
			<p>{{ t('projectmanager', 'Not sure where to start?') }}</p>
			<button type="button" class="example-btn" @click="createExample">
				{{ t('projectmanager', 'Create example project') }}
			</button>
		</template>
		<p v-else>
			{{ t('projectmanager', 'Select a project from the sidebar to get started.') }}
		</p>
	</div>
</template>

<style scoped>
.empty-state {
	padding: 40px;
	text-align: center;
	color: var(--color-text-maxcontrast);
}

.example-btn {
	margin-top: 8px;
	padding: 10px 20px;
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 600;
	cursor: pointer;
}

.example-btn:hover {
	background-color: var(--color-primary-element-hover);
}
</style>
