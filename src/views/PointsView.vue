<script setup>
import { ref, watch, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'
import StatusPill from '../components/StatusPill.vue'
import EditableCell from '../components/EditableCell.vue'
import { statusLabel } from '../utils/statusLabels.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const grid = ref(null)
const milestones = ref([])
const moduleForm = ref(null)
const pointForm = ref(null)

const POINT_STATUSES = ['todo', 'in_progress', 'partial', 'done']

async function load() {
	const [g, m] = await Promise.all([api.getProject(props.id), api.listMilestones(props.id)])
	grid.value = g
	milestones.value = m
}

watch(() => props.id, load, { immediate: true })

function milestoneName(id) {
	return milestones.value.find((m) => m.id === id)?.name ?? ''
}

function openNewModuleForm() {
	moduleForm.value = { id: null, code: '', name: '', inEstimate: true }
}

function openEditModuleForm(module) {
	moduleForm.value = { id: module.id, code: module.code, name: module.name, inEstimate: module.inEstimate }
}

function cancelModuleForm() {
	moduleForm.value = null
}

function onModuleDialogOpenChange(isOpen) {
	if (!isOpen) {
		moduleForm.value = null
	}
}

async function submitModuleForm() {
	const code = moduleForm.value.code.trim()
	const name = moduleForm.value.name.trim()
	if (!code || !name) {
		return
	}
	if (moduleForm.value.id === null) {
		await api.createModule(props.id, { code, name, inEstimate: moduleForm.value.inEstimate, sortOrder: grid.value.modules.length })
	} else {
		await api.updateModule(moduleForm.value.id, { code, name, inEstimate: moduleForm.value.inEstimate })
	}
	moduleForm.value = null
	await load()
}

const moduleDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelModuleForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function deleteModule(moduleId) {
	if (!window.confirm(t('projectmanager', 'Delete this module and all its points and leaves?'))) {
		return
	}
	await api.deleteModule(moduleId)
	await load()
}

function openNewPointForm(moduleId) {
	pointForm.value = { id: null, moduleId, code: '', description: '', estimateH: '', status: 'todo', businessValue: '', externalPending: '', milestoneId: '' }
}

function openEditPointForm(point, moduleId) {
	pointForm.value = {
		id: point.id,
		moduleId,
		code: point.code,
		description: point.description,
		estimateH: point.estimateH ?? '',
		status: point.status,
		businessValue: point.businessValue,
		externalPending: point.externalPending,
		milestoneId: point.milestoneId ?? '',
	}
}

function cancelPointForm() {
	pointForm.value = null
}

function onPointDialogOpenChange(isOpen) {
	if (!isOpen) {
		pointForm.value = null
	}
}

function findModule(moduleId) {
	return grid.value.modules.find((m) => m.id === moduleId)
}

async function submitPointForm() {
	const description = pointForm.value.description.trim()
	const code = pointForm.value.code.trim()
	if (!code || !description) {
		return
	}
	const estimateStr = String(pointForm.value.estimateH).trim()
	const estimateH = estimateStr === '' ? null : Number(estimateStr)
	const payload = {
		code,
		description,
		estimateH,
		estimateHProvided: true,
		status: pointForm.value.status,
		businessValue: pointForm.value.businessValue,
		externalPending: pointForm.value.externalPending,
		milestoneId: pointForm.value.milestoneId === '' ? null : Number(pointForm.value.milestoneId),
		milestoneIdProvided: true,
	}
	if (pointForm.value.id === null) {
		const currentCount = findModule(pointForm.value.moduleId)?.points.length ?? 0
		await api.createPoint(pointForm.value.moduleId, { ...payload, sortOrder: currentCount })
	} else {
		await api.updatePoint(pointForm.value.id, payload)
	}
	pointForm.value = null
	await load()
}

const pointDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelPointForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updatePointField(point, field, value) {
	await api.updatePoint(point.id, { [field]: value })
	await load()
}

async function updatePointStatus(point, status) {
	await api.updatePoint(point.id, { status })
	await load()
}

async function deletePoint(pointId) {
	if (!window.confirm(t('projectmanager', 'Delete this point and all its leaves?'))) {
		return
	}
	await api.deletePoint(pointId)
	await load()
}
</script>

<template>
	<div v-if="grid" class="points-view">
		<div v-for="module in grid.modules" :key="module.id" class="module-block">
			<div class="module-header">
				<EditableCell :model-value="module.code" @save="v => api.updateModule(module.id, { code: v }).then(load)" />
				<EditableCell :model-value="module.name" @save="v => api.updateModule(module.id, { name: v }).then(load)" />
				<label class="in-estimate">
					<input type="checkbox" :checked="module.inEstimate" @change="api.updateModule(module.id, { inEstimate: $event.target.checked }).then(load)">
					{{ t('projectmanager', 'Count towards the estimate') }}
				</label>
				<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" @click="openEditModuleForm(module)">
					<Pencil :size="16" />
				</button>
				<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" @click="deleteModule(module.id)">
					<Delete :size="16" />
				</button>
			</div>

			<table class="points-table">
				<thead>
					<tr>
						<th>{{ t('projectmanager', 'Point') }}</th>
						<th>{{ t('projectmanager', 'Description') }}</th>
						<th>{{ t('projectmanager', 'Est.') }}</th>
						<th>{{ t('projectmanager', 'Status') }}</th>
						<th>{{ t('projectmanager', 'Business value') }}</th>
						<th>{{ t('projectmanager', 'External pending') }}</th>
						<th>{{ t('projectmanager', 'Milestone') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="point in module.points" :key="point.id">
						<td>
							<EditableCell :model-value="point.code" @save="v => updatePointField(point, 'code', v)" />
						</td>
						<td>
							<EditableCell :model-value="point.description" @save="v => updatePointField(point, 'description', v)" />
						</td>
						<td>
							<input
								type="number" class="estimate-input"
								:value="point.estimateH ?? ''"
								placeholder="—"
								@change="api.updatePoint(point.id, { estimateH: $event.target.value === '' ? null : Number($event.target.value), estimateHProvided: true }).then(load)">
						</td>
						<td>
							<select :value="point.status" @change="updatePointStatus(point, $event.target.value)">
								<option v-for="s in POINT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
							</select>
							<StatusPill :status="point.status" />
						</td>
						<td class="text-cell">{{ point.businessValue || '—' }}</td>
						<td class="text-cell">{{ point.externalPending || '—' }}</td>
						<td>
							<span v-if="point.milestoneId" class="milestone-chip">{{ milestoneName(point.milestoneId) }}</span>
							<span v-else class="empty-hint">—</span>
						</td>
						<td class="row-actions">
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditPointForm(point, module.id)">
								<Pencil :size="16" />
							</button>
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="deletePoint(point.id)">
								<Delete :size="16" />
							</button>
						</td>
					</tr>
					<tr>
						<td colspan="8">
							<button type="button" class="link-btn" @click="openNewPointForm(module.id)">{{ t('projectmanager', '+ Point') }}</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<button type="button" class="link-btn" @click="openNewModuleForm">{{ t('projectmanager', '+ Module') }}</button>

		<NcDialog
			:open="moduleForm !== null"
			:name="moduleForm?.id === null ? t('projectmanager', 'New module') : t('projectmanager', 'Edit module')"
			is-form
			size="small"
			:buttons="moduleDialogButtons"
			@update:open="onModuleDialogOpenChange"
			@submit.prevent="submitModuleForm">
			<div v-if="moduleForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Module code (e.g. P1)') }}</span>
					<input v-model="moduleForm.code" type="text" autofocus>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Module name (e.g. Structure)') }}</span>
					<input v-model="moduleForm.name" type="text">
				</label>
				<label class="dialog-field dialog-field-checkbox">
					<input v-model="moduleForm.inEstimate" type="checkbox">
					<span>{{ t('projectmanager', 'Count towards the estimate') }}</span>
				</label>
				<p class="dialog-hint">{{ t('projectmanager', 'Uncheck this for work that falls outside what was originally planned — extra requests, bug fixes, anything not part of the initial scope. It will be labelled OTHERS and its hours are tracked separately from the estimate.') }}</p>
			</div>
		</NcDialog>

		<NcDialog
			:open="pointForm !== null"
			:name="pointForm?.id === null ? t('projectmanager', 'New point') : t('projectmanager', 'Edit point')"
			is-form
			size="normal"
			:buttons="pointDialogButtons"
			@update:open="onPointDialogOpenChange"
			@submit.prevent="submitPointForm">
			<div v-if="pointForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Point code (e.g. P1.1)') }}</span>
					<input v-model="pointForm.code" type="text" autofocus>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Point description') }}</span>
					<textarea v-model="pointForm.description" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Estimate in hours (leave empty for none)') }}</span>
					<input v-model="pointForm.estimateH" type="number" step="0.5">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Status') }}</span>
					<select v-model="pointForm.status">
						<option v-for="s in POINT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Business value') }}</span>
					<textarea v-model="pointForm.businessValue" rows="2" :placeholder="t('projectmanager', 'Fill in when this point is also something sold or promised to the client')"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'External pending') }}</span>
					<textarea v-model="pointForm.externalPending" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Presented in milestone') }}</span>
					<select v-model="pointForm.milestoneId">
						<option value="">{{ t('projectmanager', 'Not presented yet') }}</option>
						<option v-for="m in milestones" :key="m.id" :value="m.id">{{ m.name }}</option>
					</select>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.points-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.module-block {
	margin-bottom: 28px;
}

.module-header {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 8px;
	font-weight: bold;
}

.in-estimate {
	display: flex;
	align-items: center;
	gap: 4px;
	font-weight: normal;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.points-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.points-table th,
.points-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
	vertical-align: top;
}

.points-table th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.text-cell {
	max-width: 220px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.estimate-input {
	width: 70px;
}

.milestone-chip {
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

.dialog-field-checkbox {
	flex-direction: row;
	align-items: center;
}

.dialog-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.dialog-field input,
.dialog-field select,
.dialog-field textarea {
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
.dialog-field select:focus,
.dialog-field textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.dialog-hint {
	margin: -6px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
