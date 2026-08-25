<script setup>
import { ref, watch, computed } from 'vue'
import { useRouter } from 'vue-router'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import api from '../api/client.js'
import StatusPill from '../components/StatusPill.vue'
import EditableCell from '../components/EditableCell.vue'
import { statusLabel } from '../utils/statusLabels.js'
import { loadProjects } from '../store/index.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const router = useRouter()

const grid = ref(null)
const newDay = ref('')
const newDayHours = ref(7)
const moduleForm = ref(null)
const pointForm = ref(null)
const settingsForm = ref(null)
const leafForm = ref(null)

const POINT_STATUSES = ['todo', 'in_progress', 'partial', 'done']

function todayIso() {
	const d = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

async function load() {
	grid.value = await api.getProject(props.id)
}

watch(() => props.id, load, { immediate: true })

function fmtH(value) {
	return value === null || value === undefined ? '—' : Number(value).toFixed(2) + 'h'
}

function fmtPct(value) {
	return value > 0 ? Number(value).toFixed(2) + '%' : ''
}

function fmtCost(value, symbol) {
	return value === null || value === undefined ? '—' : Number(value).toFixed(2) + ' ' + symbol
}

function openSettingsForm() {
	settingsForm.value = {
		hourlyRate: grid.value.project.hourlyRate ?? '',
		currencySymbol: grid.value.project.currencySymbol,
		showCostInSummary: grid.value.project.showCostInSummary,
	}
}

function cancelSettingsForm() {
	settingsForm.value = null
}

function onSettingsDialogOpenChange(isOpen) {
	if (!isOpen) {
		settingsForm.value = null
	}
}

async function submitSettingsForm() {
	const rateStr = String(settingsForm.value.hourlyRate).trim()
	const hourlyRate = rateStr === '' ? null : Number(rateStr)
	await api.updateProject(props.id, {
		hourlyRate,
		hourlyRateProvided: true,
		currencySymbol: settingsForm.value.currencySymbol || '€',
		showCostInSummary: settingsForm.value.showCostInSummary,
	})
	settingsForm.value = null
	await load()
}

const settingsDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelSettingsForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function createExampleFromSettings() {
	const project = await api.createExampleProject()
	settingsForm.value = null
	await loadProjects()
	router.push({ name: 'grid', params: { id: project.id } })
}

async function onDayHoursChange(day, value) {
	await api.setDayHours(props.id, day, Number(value) || 0)
	await load()
}

async function addDay() {
	if (!newDay.value) {
		return
	}
	await api.setDayHours(props.id, newDay.value, Number(newDayHours.value) || 0)
	newDay.value = ''
	await load()
}

async function deleteDay(day) {
	if (!window.confirm(t('projectmanager', 'Delete the hours entry for this day? If leaves are still logged on this day, the column stays but the hours reset to 0.'))) {
		return
	}
	await api.deleteDayHours(props.id, day)
	await load()
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
	const name = moduleForm.value.name.trim()
	if (!name) {
		window.alert(t('projectmanager', 'Please name the module.'))
		return
	}
	const code = moduleForm.value.code.trim() || name.slice(0, 4).toUpperCase()
	if (moduleForm.value.id === null) {
		await api.createModule(props.id, { code, name, inEstimate: moduleForm.value.inEstimate, sortOrder: grid.value?.modules.length ?? 0 })
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

async function updateModuleField(module, field, value) {
	await api.updateModule(module.id, { [field]: value })
	await load()
}

function openNewPointForm(moduleId) {
	pointForm.value = { id: null, moduleId, code: '', description: '', estimateH: '' }
}

function openEditPointForm(point) {
	pointForm.value = { id: point.id, moduleId: point.moduleId, code: point.code, description: point.description, estimateH: point.estimateH ?? '' }
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
	return (grid.value?.modules ?? []).find((m) => m.id === moduleId) ?? null
}

async function submitPointForm() {
	const description = pointForm.value.description.trim()
	if (!description) {
		window.alert(t('projectmanager', 'Please describe the point.'))
		return
	}
	const estimateStr = String(pointForm.value.estimateH).trim()
	const estimateH = estimateStr === '' ? null : Number(estimateStr)
	const code = pointForm.value.code.trim()
	if (pointForm.value.id === null) {
		const currentCount = findModule(pointForm.value.moduleId)?.points.length ?? 0
		await api.createPoint(pointForm.value.moduleId, { code, description, estimateH, status: 'todo', sortOrder: currentCount })
	} else {
		await api.updatePoint(pointForm.value.id, { code, description, estimateH, estimateHProvided: true })
	}
	pointForm.value = null
	await load()
}

const pointDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelPointForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updatePointStatus(point, status) {
	await api.updatePoint(point.id, { status })
	await load()
}

async function updatePointField(point, field, value) {
	await api.updatePoint(point.id, { [field]: value })
	await load()
}

async function deletePoint(pointId) {
	if (!window.confirm(t('projectmanager', 'Delete this point and all its leaves?'))) {
		return
	}
	await api.deletePoint(pointId)
	await load()
}

function openNewLeafForm(pointId) {
	leafForm.value = { pointId, leafId: null, description: '', workDate: todayIso() }
}

function openEditLeafForm(leaf) {
	leafForm.value = { pointId: leaf.pointId, leafId: leaf.id, description: leaf.description, workDate: leaf.workDate }
}

function cancelLeafForm() {
	leafForm.value = null
}

function onLeafDialogOpenChange(isOpen) {
	if (!isOpen) {
		leafForm.value = null
	}
}

function findPoint(pointId) {
	for (const module of grid.value?.modules ?? []) {
		const point = module.points.find((p) => p.id === pointId)
		if (point) {
			return point
		}
	}
	return null
}

async function submitLeafForm() {
	const description = leafForm.value.description.trim()
	if (!description) {
		window.alert(t('projectmanager', 'Please describe what was done.'))
		return
	}
	if (!leafForm.value.workDate) {
		window.alert(t('projectmanager', 'Please pick a date.'))
		return
	}
	if (leafForm.value.leafId === null) {
		const currentCount = findPoint(leafForm.value.pointId)?.leaves.length ?? 0
		await api.createLeaf(leafForm.value.pointId, { description, workDate: leafForm.value.workDate, sortOrder: currentCount })
	} else {
		await api.updateLeaf(leafForm.value.leafId, { description, workDate: leafForm.value.workDate })
	}
	leafForm.value = null
	await load()
}

const leafDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelLeafForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updateLeafField(leaf, field, value) {
	await api.updateLeaf(leaf.id, { [field]: value })
	await load()
}

async function deleteLeaf(leafId) {
	await api.deleteLeaf(leafId)
	await load()
}

const dayHeaders = computed(() => grid.value?.days ?? [])
</script>

<template>
	<div v-if="grid" class="grid-view">
		<div class="grid-scroll">
			<table class="tracker-table">
				<thead>
					<tr class="header-row">
						<th class="col-point">{{ t('projectmanager', 'Point') }}</th>
						<th class="col-module">{{ t('projectmanager', 'Module') }}</th>
						<th class="col-desc">{{ t('projectmanager', 'Description') }}</th>
						<th class="col-num">{{ t('projectmanager', 'Est.') }}</th>
						<th class="col-num">{{ t('projectmanager', 'Done') }}</th>
						<th class="col-num">{{ t('projectmanager', 'Rem.') }}</th>
						<th class="col-status">{{ t('projectmanager', 'Status') }}</th>
						<th v-for="day in dayHeaders" :key="day" class="col-day day-header">
							{{ day }}
							<button type="button" class="icon-btn day-delete-btn" :aria-label="t('projectmanager', 'Delete this day')" :title="t('projectmanager', 'Delete this day')" @click="deleteDay(day)">
								<Delete :size="14" />
							</button>
						</th>
						<th class="col-add-day">
							<input v-model="newDay" type="date" class="new-day-input">
							<input v-model="newDayHours" type="number" step="0.5" class="new-day-hours">
							<button type="button" @click="addDay">+</button>
						</th>
					</tr>
					<tr class="hours-row">
						<td colspan="6"></td>
						<td>{{ t('projectmanager', 'Hours/day') }}</td>
						<td v-for="day in dayHeaders" :key="day" class="hours-cell">
							<input
								type="number"
								step="0.5"
								class="hours-input"
								:value="grid.hoursByDay[day] ?? 0"
								@change="onDayHoursChange(day, $event.target.value)">
							<span class="hours-unit">h</span>
						</td>
						<td></td>
					</tr>
				</thead>
				<tbody>
					<template v-for="module in grid.modules" :key="module.id">
						<tr class="module-row">
							<td></td>
							<td>
								<EditableCell :model-value="module.code" @save="v => updateModuleField(module, 'code', v)" />
							</td>
							<td>
								<EditableCell :model-value="module.name" @save="v => updateModuleField(module, 'name', v)" />
								<span v-if="!module.inEstimate" class="others-badge">{{ t('projectmanager', 'OTHERS') }}</span>
							</td>
							<td class="col-num">{{ fmtH(module.estimateH) }}</td>
							<td class="col-num">{{ fmtH(module.doneH) }}</td>
							<td class="col-num">{{ fmtH(module.remainingH) }}</td>
							<td class="row-actions">
								<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditModuleForm(module)">
									<Pencil :size="16" />
								</button>
								<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="deleteModule(module.id)">
									<Delete :size="16" />
								</button>
							</td>
							<td v-for="day in dayHeaders" :key="day" class="col-day">{{ fmtPct(module.pctByDay[day]) }}</td>
							<td></td>
						</tr>
						<template v-for="point in module.points" :key="point.id">
							<tr class="point-row">
								<td>
									<EditableCell :model-value="point.code" @save="v => updatePointField(point, 'code', v)" />
								</td>
								<td></td>
								<td class="col-desc">
									<EditableCell :model-value="point.description" @save="v => updatePointField(point, 'description', v)" />
								</td>
								<td class="col-num">
									<input
										type="number"
										class="estimate-input"
										:value="point.estimateH ?? ''"
										placeholder="—"
										@change="updatePointEstimate(point, $event.target.value)">
								</td>
								<td class="col-num">{{ fmtH(point.doneH) }}</td>
								<td class="col-num">{{ fmtH(point.remainingH) }}</td>
								<td>
									<select :value="point.status" @change="updatePointStatus(point, $event.target.value)">
										<option v-for="s in POINT_STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
									</select>
									<StatusPill :status="point.status" />
								</td>
								<td v-for="day in dayHeaders" :key="day" class="col-day">{{ fmtPct(point.pctByDay[day]) }}</td>
								<td class="row-actions">
									<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditPointForm(point)">
										<Pencil :size="16" />
									</button>
									<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="deletePoint(point.id)">
										<Delete :size="16" />
									</button>
									<button type="button" class="link-btn" @click="openNewLeafForm(point.id)">{{ t('projectmanager', '+ Leaf') }}</button>
								</td>
							</tr>
							<tr v-for="leaf in point.leaves" :key="leaf.id" class="leaf-row">
								<td class="leaf-lead-cell"></td>
								<td class="leaf-lead-cell"></td>
								<td class="leaf-desc">
									<span class="leaf-arrow">↳</span>
									<EditableCell :model-value="leaf.description" @save="v => updateLeafField(leaf, 'description', v)" />
								</td>
								<td></td>
								<td></td>
								<td></td>
								<td></td>
								<td v-for="day in dayHeaders" :key="day" class="col-day">
									<span v-if="day === leaf.workDate">X</span>
								</td>
								<td class="row-actions">
									<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditLeafForm(leaf)">
										<Pencil :size="16" />
									</button>
									<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="deleteLeaf(leaf.id)">
										<Delete :size="16" />
									</button>
								</td>
							</tr>
						</template>
						<tr class="add-point-row">
							<td colspan="7">
								<button type="button" class="link-btn" @click="openNewPointForm(module.id)">{{ t('projectmanager', '+ Point') }}</button>
							</td>
							<td :colspan="dayHeaders.length + 1"></td>
						</tr>
					</template>
					<tr class="add-module-row">
						<td :colspan="7 + dayHeaders.length + 1">
							<button type="button" class="link-btn" @click="openNewModuleForm">{{ t('projectmanager', '+ Module') }}</button>
						</td>
					</tr>
					<tr class="total-row">
						<td colspan="6"></td>
						<td>{{ t('projectmanager', 'TOTAL/DAY') }}</td>
						<td v-for="day in dayHeaders" :key="day" class="col-day">{{ fmtPct(grid.totalPctByDay[day]) }}</td>
						<td></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="summary">
			<div class="summary-header">
				<h3>{{ t('projectmanager', 'Summary') }}</h3>
				<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Project settings')" :title="t('projectmanager', 'Project settings')" @click="openSettingsForm">
					<Cog :size="18" />
				</button>
			</div>
			<table class="summary-table">
				<tbody>
					<tr>
						<td>{{ t('projectmanager', 'Estimated (in scope)') }}</td>
						<td>{{ fmtH(grid.summary.estimatedH) }}</td>
						<td>{{ grid.summary.estimatedDays }}d</td>
						<td v-if="grid.summary.costEnabled">{{ fmtCost(grid.summary.estimatedCost, grid.summary.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (in scope)') }}</td>
						<td>{{ fmtH(grid.summary.doneInScopeH) }}</td>
						<td>{{ grid.summary.doneInScopeDays }}d</td>
						<td v-if="grid.summary.costEnabled">{{ fmtCost(grid.summary.doneInScopeCost, grid.summary.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (OTHERS)') }}</td>
						<td>{{ fmtH(grid.summary.doneOthersH) }}</td>
						<td>{{ grid.summary.doneOthersDays }}d</td>
						<td v-if="grid.summary.costEnabled">{{ fmtCost(grid.summary.doneOthersCost, grid.summary.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Done (total)') }}</td>
						<td>{{ fmtH(grid.summary.doneTotalH) }}</td>
						<td>{{ grid.summary.doneTotalDays }}d</td>
						<td v-if="grid.summary.costEnabled">{{ fmtCost(grid.summary.doneTotalCost, grid.summary.currencySymbol) }}</td>
					</tr>
					<tr>
						<td>{{ t('projectmanager', 'Remaining (in scope)') }}</td>
						<td>{{ fmtH(grid.summary.remainingH) }}</td>
						<td>{{ grid.summary.remainingDays }}d</td>
						<td v-if="grid.summary.costEnabled">{{ fmtCost(grid.summary.remainingCost, grid.summary.currencySymbol) }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
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
				<span class="dialog-label">{{ t('projectmanager', 'Module name (e.g. Structure)') }}</span>
				<input v-model="moduleForm.name" type="text" autofocus>
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Module code (e.g. P1)') }}</span>
				<input v-model="moduleForm.code" type="text">
			</label>
			<label class="dialog-field dialog-field-checkbox">
				<input v-model="moduleForm.inEstimate" type="checkbox">
				<span>{{ t('projectmanager', 'Count towards the estimate') }}</span>
			</label>
			<p class="dialog-hint">
				{{ t('projectmanager', 'Uncheck this for work that falls outside what was originally planned — extra requests, bug fixes, anything not part of the initial scope. It will be labelled OTHERS and its hours are tracked separately from the estimate.') }}
			</p>
		</div>
	</NcDialog>

	<NcDialog
		:open="pointForm !== null"
		:name="pointForm?.id === null ? t('projectmanager', 'New point') : t('projectmanager', 'Edit point')"
		is-form
		size="small"
		:buttons="pointDialogButtons"
		@update:open="onPointDialogOpenChange"
		@submit.prevent="submitPointForm">
		<div v-if="pointForm" class="dialog-form">
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Point description') }}</span>
				<textarea v-model="pointForm.description" rows="3" autofocus></textarea>
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Point code (e.g. P1.1)') }}</span>
				<input v-model="pointForm.code" type="text">
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Estimate in hours (leave empty for none)') }}</span>
				<input v-model="pointForm.estimateH" type="number" step="0.5">
			</label>
		</div>
	</NcDialog>

	<NcDialog
		:open="leafForm !== null"
		:name="leafForm?.leafId === null ? t('projectmanager', 'New leaf') : t('projectmanager', 'Edit leaf')"
		is-form
		size="small"
		:buttons="leafDialogButtons"
		@update:open="onLeafDialogOpenChange"
		@submit.prevent="submitLeafForm">
		<div v-if="leafForm" class="dialog-form">
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'What was done?') }}</span>
				<textarea v-model="leafForm.description" rows="3" autofocus></textarea>
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Date') }}</span>
				<input v-model="leafForm.workDate" type="date">
			</label>
		</div>
	</NcDialog>

	<NcDialog
		:open="settingsForm !== null"
		:name="t('projectmanager', 'Project settings')"
		is-form
		size="small"
		:buttons="settingsDialogButtons"
		@update:open="onSettingsDialogOpenChange"
		@submit.prevent="submitSettingsForm">
		<div v-if="settingsForm" class="dialog-form">
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Hourly rate') }}</span>
				<input v-model="settingsForm.hourlyRate" type="number" step="0.01" :placeholder="t('projectmanager', 'Leave empty to disable')">
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Currency symbol') }}</span>
				<input v-model="settingsForm.currencySymbol" type="text" maxlength="8">
			</label>
			<p class="dialog-hint">{{ t('projectmanager', 'The currency symbol is shown after the value, e.g. "350.00 €".') }}</p>
			<label class="dialog-field dialog-field-checkbox">
				<input v-model="settingsForm.showCostInSummary" type="checkbox">
				<span>{{ t('projectmanager', 'Show cost in the summary') }}</span>
			</label>
			<hr class="dialog-separator">
			<button type="button" class="link-btn" @click="createExampleFromSettings">
				{{ t('projectmanager', 'Create example project') }}
			</button>
		</div>
	</NcDialog>
</template>

<style scoped>
.grid-view {
	padding: 16px;
	box-sizing: border-box;
	height: 100%;
	overflow-y: auto;
}

.grid-scroll {
	overflow: auto;
	max-height: 60vh;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	margin-bottom: 24px;
}

.tracker-table {
	border-collapse: collapse;
	font-size: 13px;
	min-width: 100%;
}

.tracker-table th,
.tracker-table td {
	border: 1px solid var(--color-border);
	padding: 4px 8px;
	white-space: nowrap;
}

.col-desc {
	white-space: nowrap;
	max-width: 360px;
	overflow: hidden;
	text-overflow: ellipsis;
}

.col-num {
	text-align: right;
}

.header-row th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	position: sticky;
	top: 0;
	z-index: 2;
}

.col-point,
.col-module,
.col-desc {
	position: sticky;
	background-color: inherit;
	z-index: 1;
}

.col-point {
	left: 0;
}

.col-module {
	left: 60px;
}

.col-desc {
	left: 120px;
}

/* Module group rows: a subtly different surface + accent stripe, instead of
   a flat fill color, so it stays legible in both light and dark themes. */
.module-row {
	font-weight: bold;
	background-color: var(--color-background-dark);
	box-shadow: inset 4px 0 0 var(--color-primary-element);
}

/* Point rows get a lighter accent stripe to read as "nested under" a module,
   without a full-row fill that would clash with dark theme. */
.point-row {
	background-color: var(--color-main-background);
	box-shadow: inset 3px 0 0 var(--color-primary-element-light);
}

/* Leaves are one level deeper than points: a receded background tint, a
   thinner/muted accent stripe and a smaller font make the nesting obvious
   at a glance, instead of relying on text color alone. */
.leaf-row {
	background-color: var(--color-background-hover);
	color: var(--color-text-maxcontrast);
	font-size: 12px;
	box-shadow: inset 2px 0 0 var(--color-border-dark);
}

.leaf-lead-cell {
	background-color: var(--color-main-background);
}

.leaf-desc {
	padding-left: 8px;
}

.leaf-arrow {
	display: inline-block;
	margin-right: 6px;
	opacity: 0.6;
}

.total-row {
	background-color: var(--color-background-dark);
	font-weight: bold;
	box-shadow: inset 0 2px 0 var(--color-primary-element);
}

.others-badge {
	font-size: 11px;
	background-color: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	border-radius: 8px;
	padding: 1px 6px;
	margin-left: 6px;
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	padding: 0 4px;
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

.day-header {
	position: relative;
	padding-right: 20px !important;
}

.day-delete-btn {
	position: absolute;
	top: 2px;
	right: 2px;
	width: 16px;
	height: 16px;
	color: var(--color-primary-element-text);
	opacity: 0.6;
}

.day-delete-btn:hover {
	opacity: 1;
	background-color: rgba(0, 0, 0, 0.15);
	color: var(--color-primary-element-text);
}

.hours-input,
.estimate-input {
	width: 60px;
	padding: 2px 4px;
	box-sizing: border-box;
}

.hours-cell {
	white-space: nowrap;
}

.hours-input {
	width: 48px;
	text-align: right;
}

.hours-unit {
	color: var(--color-text-maxcontrast);
	margin-left: 2px;
}

.leaf-form-row {
	background-color: var(--color-background-hover);
}

.new-leaf-desc {
	width: 100%;
	min-width: 200px;
	box-sizing: border-box;
}

.new-leaf-date {
	width: 150px;
}

.new-day-input {
	width: 130px;
}

.new-day-hours {
	width: 50px;
}

.summary {
	max-width: 620px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	overflow: hidden;
	box-shadow: inset 0 3px 0 var(--color-primary-element);
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
	padding: 8px 16px;
}

.summary-table tr:last-child td {
	font-weight: bold;
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

.dialog-field input[type="text"],
.dialog-field input[type="number"],
.dialog-field input[type="date"],
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
.dialog-field textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.dialog-field-checkbox {
	flex-direction: row;
	align-items: center;
	gap: 8px;
}

.dialog-hint {
	margin: -8px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.dialog-separator {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 4px 0;
}
</style>
