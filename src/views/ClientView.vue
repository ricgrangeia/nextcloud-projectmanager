<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import api from '../api/client.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const router = useRouter()
const data = ref(null)

async function load() {
	data.value = await api.getClient(props.id)
}

watch(() => props.id, load, { immediate: true })

function fmtH(value) {
	return value === null || value === undefined ? '—' : Number(value).toFixed(2) + 'h'
}

function fmtCost(value, symbol) {
	return value === null || value === undefined ? '—' : Number(value).toFixed(2) + ' ' + symbol
}

function openProject(project) {
	router.push({ name: 'grid', params: { id: project.id } })
}
</script>

<template>
	<div v-if="data" class="client-view">
		<h2>{{ data.client.name }}</h2>

		<div class="summary">
			<div class="summary-header">
				<h3>{{ t('projectmanager', 'Summary') }}</h3>
			</div>
			<table class="summary-table">
				<tbody>
					<tr>
						<td>{{ t('projectmanager', 'Estimated (in scope)') }}</td>
						<td>{{ fmtH(data.totals.estimatedH) }}</td>
						<td v-if="data.costEnabled">{{ fmtCost(data.totals.estimatedCost, data.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (in scope)') }}</td>
						<td>{{ fmtH(data.totals.doneInScopeH) }}</td>
						<td v-if="data.costEnabled">{{ fmtCost(data.totals.doneInScopeCost, data.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (OTHERS)') }}</td>
						<td>{{ fmtH(data.totals.doneOthersH) }}</td>
						<td v-if="data.costEnabled">{{ fmtCost(data.totals.doneOthersCost, data.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (total)') }}</td>
						<td>{{ fmtH(data.totals.doneTotalH) }}</td>
						<td v-if="data.costEnabled">{{ fmtCost(data.totals.doneTotalCost, data.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Remaining (in scope)') }}</td>
						<td>{{ fmtH(data.totals.remainingH) }}</td>
						<td v-if="data.costEnabled">{{ fmtCost(data.totals.remainingCost, data.currencySymbol) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<h3 class="projects-title">{{ t('projectmanager', 'Projects') }}</h3>
		<table class="projects-table">
			<thead>
				<tr>
					<th>{{ t('projectmanager', 'Name') }}</th>
					<th>{{ t('projectmanager', 'Estimated (in scope)') }}</th>
					<th>{{ t('projectmanager', 'Done (in scope)') }}</th>
					<th>{{ t('projectmanager', 'Remaining (in scope)') }}</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="project in data.projects" :key="project.id" class="project-row" @click="openProject(project)">
					<td>
						{{ project.name }}
						<span v-if="project.archived" class="archived-tag">({{ t('projectmanager', 'archived') }})</span>
					</td>
					<td>{{ fmtH(project.summary.estimatedH) }}</td>
					<td>{{ fmtH(project.summary.doneInScopeH) }}</td>
					<td>{{ fmtH(project.summary.remainingH) }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style scoped>
.client-view {
	padding: 16px;
	box-sizing: border-box;
	height: 100%;
	overflow-y: auto;
}

.client-view h2 {
	margin: 0 0 16px;
}

.summary {
	max-width: 620px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	box-shadow: inset 0 3px 0 var(--color-primary-element);
	margin-bottom: 24px;
}

.summary-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin: 8px 16px 4px;
}

.summary-header h3 {
	margin: 0;
}

.summary-table {
	border-collapse: collapse;
	width: 100%;
}

.summary-table tr:nth-child(odd) {
	background-color: var(--color-background-hover);
}

.summary-table td {
	border-top: 1px solid var(--color-border);
	padding: 6px 16px;
}

.summary-table tr:last-child td {
	font-weight: 600;
}

.projects-title {
	margin: 0 0 8px;
}

.projects-table {
	border-collapse: collapse;
	max-width: 720px;
	width: 100%;
}

.projects-table th {
	text-align: left;
	padding: 6px 16px;
	color: var(--color-text-maxcontrast);
	border-bottom: 1px solid var(--color-border);
}

.projects-table td {
	padding: 6px 16px;
	border-top: 1px solid var(--color-border);
}

.project-row {
	cursor: pointer;
}

.project-row:hover {
	background-color: var(--color-background-hover);
}

.archived-tag {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
