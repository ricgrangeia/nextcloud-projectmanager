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
const tests = ref([])
const testForm = ref(null)
const STATUSES = ['to_test', 'passed', 'failed']

function todayIso() {
	const d = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

async function load() {
	const [g, t] = await Promise.all([api.getProject(props.id), api.listTests(props.id)])
	grid.value = g
	tests.value = t
}

watch(() => props.id, load, { immediate: true })

const testsByPoint = computed(() => {
	const map = {}
	for (const test of tests.value) {
		const key = test.pointId ?? 'general'
		;(map[key] ??= []).push(test)
	}
	return map
})

const generalTests = computed(() => testsByPoint.value.general ?? [])

const counts = computed(() => {
	const c = { passed: 0, failed: 0, to_test: 0 }
	for (const test of tests.value) {
		c[test.status] = (c[test.status] ?? 0) + 1
	}
	return c
})

function openNewTestForm(pointId = null) {
	testForm.value = { id: null, pointId, area: '', profile: '', scenario: '', expected: '', status: 'to_test', testDate: todayIso(), notes: '' }
}

function openEditTestForm(test) {
	testForm.value = { ...test, testDate: test.testDate ?? '' }
}

function cancelTestForm() {
	testForm.value = null
}

function onTestDialogOpenChange(isOpen) {
	if (!isOpen) {
		testForm.value = null
	}
}

async function submitTestForm() {
	const area = testForm.value.area.trim()
	if (!area) {
		window.alert(t('projectmanager', 'Please fill in the area.'))
		return
	}
	const payload = {
		area,
		profile: testForm.value.profile,
		scenario: testForm.value.scenario,
		expected: testForm.value.expected,
		status: testForm.value.status,
		testDate: testForm.value.testDate || null,
		notes: testForm.value.notes,
		pointId: testForm.value.pointId === '' ? null : testForm.value.pointId,
		pointIdProvided: true,
	}
	if (testForm.value.id === null) {
		await api.createTest(props.id, payload)
	} else {
		await api.updateTest(testForm.value.id, payload)
	}
	testForm.value = null
	await load()
}

const testDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelTestForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updateStatus(test, status) {
	await api.updateTest(test.id, { status })
	await load()
}

async function updateField(test, field, value) {
	await api.updateTest(test.id, { [field]: value })
	await load()
}

async function removeTest(id) {
	if (!window.confirm(t('projectmanager', 'Delete this test?'))) {
		return
	}
	await api.deleteTest(id)
	await load()
}
</script>

<template>
	<div v-if="grid" class="tests-view">
		<div class="toolbar">
			<span class="counts">
				{{ t('projectmanager', 'Passed') }}: {{ counts.passed }} ·
				{{ t('projectmanager', 'Failed') }}: {{ counts.failed }} ·
				{{ t('projectmanager', 'To test') }}: {{ counts.to_test }}
			</span>
		</div>

		<div v-for="module in grid.modules" :key="module.id" class="section">
			<h3>{{ module.code }} — {{ module.name }}</h3>
			<div v-for="point in module.points" :key="point.id" class="point-block">
				<div class="point-header">
					<strong>{{ point.code }}</strong> {{ point.description }}
					<button type="button" class="link-btn" @click="openNewTestForm(point.id)">{{ t('projectmanager', '+ Test') }}</button>
				</div>
				<table v-if="(testsByPoint[point.id] || []).length" class="tests-table">
					<tbody>
						<tr v-for="test in testsByPoint[point.id]" :key="test.id">
							<td class="col-area">
								<EditableCell :model-value="test.area" @save="v => updateField(test, 'area', v)" />
							</td>
							<td>
								<EditableCell :model-value="test.scenario" @save="v => updateField(test, 'scenario', v)" placeholder="—" />
							</td>
							<td>
								<select :value="test.status" @change="updateStatus(test, $event.target.value)">
									<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
								</select>
								<StatusPill :status="test.status" />
							</td>
							<td>{{ test.testDate }}</td>
							<td class="row-actions">
								<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditTestForm(test)">
									<Pencil :size="16" />
								</button>
								<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="removeTest(test.id)">
									<Delete :size="16" />
								</button>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="section">
			<h3>{{ t('projectmanager', 'General') }}</h3>
			<button type="button" class="link-btn" @click="openNewTestForm(null)">{{ t('projectmanager', '+ Test') }}</button>
			<table v-if="generalTests.length" class="tests-table">
				<tbody>
					<tr v-for="test in generalTests" :key="test.id">
						<td class="col-area">
							<EditableCell :model-value="test.area" @save="v => updateField(test, 'area', v)" />
						</td>
						<td>
							<EditableCell :model-value="test.scenario" @save="v => updateField(test, 'scenario', v)" placeholder="—" />
						</td>
						<td>
							<select :value="test.status" @change="updateStatus(test, $event.target.value)">
								<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
							</select>
							<StatusPill :status="test.status" />
						</td>
						<td>{{ test.testDate }}</td>
						<td class="row-actions">
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditTestForm(test)">
								<Pencil :size="16" />
							</button>
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="removeTest(test.id)">
								<Delete :size="16" />
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcDialog
			:open="testForm !== null"
			:name="testForm?.id === null ? t('projectmanager', '+ Test') : t('projectmanager', 'Edit')"
			is-form
			size="normal"
			:buttons="testDialogButtons"
			@update:open="onTestDialogOpenChange"
			@submit.prevent="submitTestForm">
			<div v-if="testForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Point') }}</span>
					<select v-model="testForm.pointId">
						<option :value="null">{{ t('projectmanager', 'General') }}</option>
						<optgroup v-for="module in grid.modules" :key="module.id" :label="`${module.code} — ${module.name}`">
							<option v-for="point in module.points" :key="point.id" :value="point.id">{{ point.code }} — {{ point.description }}</option>
						</optgroup>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Area') }}</span>
					<input v-model="testForm.area" type="text" autofocus>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Profile') }}</span>
					<input v-model="testForm.profile" type="text">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Scenario/Action') }}</span>
					<textarea v-model="testForm.scenario" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Expected result') }}</span>
					<textarea v-model="testForm.expected" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Status') }}</span>
					<select v-model="testForm.status">
						<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Date') }}</span>
					<input v-model="testForm.testDate" type="date">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Notes') }}</span>
					<textarea v-model="testForm.notes" rows="2"></textarea>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.tests-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	margin-bottom: 16px;
	display: flex;
	gap: 24px;
	align-items: center;
}

.counts {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.section {
	margin-bottom: 24px;
}

.section h3 {
	margin-bottom: 8px;
}

.point-block {
	margin: 6px 0 6px 12px;
}

.point-header {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	margin-bottom: 4px;
}

.tests-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
	margin-bottom: 8px;
}

.tests-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.col-area {
	width: 160px;
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

.dialog-field input[type="text"],
.dialog-field input[type="date"],
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
</style>
