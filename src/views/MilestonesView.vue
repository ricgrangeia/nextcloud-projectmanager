<script setup>
import { ref, watch, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import api from '../api/client.js'
import { channelLabel, clientStatusLabel } from '../utils/statusLabels.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const milestones = ref([])
const pointsByMilestone = ref({})
const form = ref(null)

const CHANNELS = ['email', 'session']
const CLIENT_STATUSES = ['pending', 'presented', 'validated']

async function load() {
	const [list, grid] = await Promise.all([api.listMilestones(props.id), api.getProject(props.id)])
	milestones.value = list

	const byMilestone = {}
	for (const module of grid.modules) {
		for (const point of module.points) {
			if (point.milestoneId !== null) {
				(byMilestone[point.milestoneId] ??= []).push({ ...point, moduleCode: module.code })
			}
		}
	}
	pointsByMilestone.value = byMilestone
}

watch(() => props.id, load, { immediate: true })

function todayIso() {
	const d = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function openNewForm() {
	form.value = { id: null, name: '', targetDate: '', reachedDate: '', communicationChannel: '', clientStatus: 'pending', acceptancePct: '' }
}

function openEditForm(m) {
	form.value = { ...m, targetDate: m.targetDate ?? '', reachedDate: m.reachedDate ?? '', communicationChannel: m.communicationChannel ?? '', acceptancePct: m.acceptancePct ?? '' }
}

function cancelForm() {
	form.value = null
}

function onDialogOpenChange(isOpen) {
	if (!isOpen) {
		form.value = null
	}
}

async function submitForm() {
	const name = form.value.name.trim()
	if (!name) {
		return
	}
	const payload = {
		name,
		targetDate: form.value.targetDate || null,
		targetDateProvided: true,
		reachedDate: form.value.reachedDate || null,
		reachedDateProvided: true,
		communicationChannel: form.value.communicationChannel || null,
		clientStatus: form.value.clientStatus,
		acceptancePct: form.value.acceptancePct === '' ? null : Number(form.value.acceptancePct),
	}
	if (form.value.id === null) {
		await api.createMilestone(props.id, payload)
	} else {
		await api.updateMilestone(form.value.id, payload)
	}
	form.value = null
	await load()
}

const dialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function toggleReached(m) {
	await api.updateMilestone(m.id, { reachedDateProvided: true, reachedDate: m.reachedDate ? null : todayIso() })
	await load()
}

async function updateField(m, field, value) {
	await api.updateMilestone(m.id, { [field]: value })
	await load()
}

async function removeMilestone(id) {
	if (!window.confirm(t('projectmanager', 'Delete this milestone?'))) {
		return
	}
	await api.deleteMilestone(id)
	await load()
}
</script>

<template>
	<div class="milestones-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewForm">{{ t('projectmanager', '+ Milestone') }}</button>
		</div>

		<table class="milestones-table">
			<thead>
				<tr>
					<th></th>
					<th>{{ t('projectmanager', 'Milestone name') }}</th>
					<th>{{ t('projectmanager', 'Target date') }}</th>
					<th>{{ t('projectmanager', 'Reached') }}</th>
					<th>{{ t('projectmanager', 'Channel') }}</th>
					<th>{{ t('projectmanager', 'Client status') }}</th>
					<th>{{ t('projectmanager', 'Acceptance %') }}</th>
					<th>{{ t('projectmanager', 'Points presented') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="m in milestones" :key="m.id">
					<td>
						<input type="checkbox" :checked="!!m.reachedDate" @change="toggleReached(m)">
					</td>
					<td>{{ m.name }}</td>
					<td>{{ m.targetDate || '—' }}</td>
					<td>{{ m.reachedDate || '—' }}</td>
					<td>
						<select :value="m.communicationChannel || ''" @change="updateField(m, 'communicationChannel', $event.target.value || null)">
							<option value="">—</option>
							<option v-for="c in CHANNELS" :key="c" :value="c">{{ channelLabel(c) }}</option>
						</select>
					</td>
					<td>
						<select :value="m.clientStatus" @change="updateField(m, 'clientStatus', $event.target.value)">
							<option v-for="s in CLIENT_STATUSES" :key="s" :value="s">{{ clientStatusLabel(s) }}</option>
						</select>
					</td>
					<td>
						<input
							type="number" min="0" max="100" class="pct-input"
							:value="m.acceptancePct ?? ''"
							placeholder="—"
							@change="updateField(m, 'acceptancePct', $event.target.value === '' ? null : Number($event.target.value))">
					</td>
					<td class="points-cell">
						<span v-if="!(pointsByMilestone[m.id]?.length)" class="empty-hint">{{ t('projectmanager', 'None yet') }}</span>
						<span v-for="p in pointsByMilestone[m.id] || []" :key="p.id" class="point-chip">{{ p.moduleCode }} {{ p.code }}</span>
					</td>
					<td class="row-actions">
						<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditForm(m)">
							<Pencil :size="16" />
						</button>
						<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="removeMilestone(m.id)">
							<Delete :size="16" />
						</button>
					</td>
				</tr>
				<tr v-if="milestones.length === 0">
					<td colspan="9" class="empty-hint">{{ t('projectmanager', 'No milestones yet.') }}</td>
				</tr>
			</tbody>
		</table>

		<NcDialog
			:open="form !== null"
			:name="form?.id === null ? t('projectmanager', '+ Milestone') : t('projectmanager', 'Edit')"
			is-form
			size="small"
			:buttons="dialogButtons"
			@update:open="onDialogOpenChange"
			@submit.prevent="submitForm">
			<div v-if="form" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Milestone name') }}</span>
					<input v-model="form.name" type="text" autofocus required>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Target date') }}</span>
					<input v-model="form.targetDate" type="date">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Reached date') }}</span>
					<input v-model="form.reachedDate" type="date">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Channel') }}</span>
					<select v-model="form.communicationChannel">
						<option value="">—</option>
						<option v-for="c in CHANNELS" :key="c" :value="c">{{ channelLabel(c) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Client status') }}</span>
					<select v-model="form.clientStatus">
						<option v-for="s in CLIENT_STATUSES" :key="s" :value="s">{{ clientStatusLabel(s) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Acceptance %') }}</span>
					<input v-model="form.acceptancePct" type="number" min="0" max="100">
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.milestones-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	margin-bottom: 16px;
}

.milestones-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.milestones-table th,
.milestones-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.milestones-table th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.pct-input {
	width: 64px;
}

.points-cell {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.point-chip {
	display: inline-block;
	padding: 1px 8px;
	border-radius: var(--border-radius-pill, 16px);
	background-color: var(--color-background-dark);
	font-size: 12px;
	white-space: nowrap;
}

.empty-hint {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 12px;
}

.row-actions {
	white-space: nowrap;
}

.icon-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	vertical-align: middle;
}

.icon-btn:hover {
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
}

.dialog-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 4px 0 16px;
}

.dialog-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 13px;
}

.dialog-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.dialog-field input,
.dialog-field select {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus,
.dialog-field select:focus {
	border-color: var(--color-primary-element);
	outline: none;
}
</style>
