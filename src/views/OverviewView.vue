<script setup>
import { ref, watch, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Delete from 'vue-material-design-icons/Delete.vue'
import Star from 'vue-material-design-icons/Star.vue'
import AlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'
import api from '../api/client.js'
import StatusPill from '../components/StatusPill.vue'
import { phaseLabel } from '../utils/statusLabels.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const overview = ref(null)
const milestoneForm = ref(null)
const briefDraft = ref('')
const waitingDraft = ref('')

async function load() {
	overview.value = await api.getOverview(props.id)
	briefDraft.value = overview.value.brief
	waitingDraft.value = overview.value.waitingOnClient
}

watch(() => props.id, load, { immediate: true })

function fmtH(value) {
	return value === null || value === undefined ? '—' : Number(value).toFixed(2) + 'h'
}

function fmtDate(value) {
	return value || '—'
}

async function saveBrief() {
	if (briefDraft.value === overview.value.brief) {
		return
	}
	await api.updateProject(props.id, { brief: briefDraft.value })
	await load()
}

async function saveWaiting() {
	if (waitingDraft.value === overview.value.waitingOnClient) {
		return
	}
	await api.updateProject(props.id, { waitingOnClient: waitingDraft.value })
	await load()
}

function openNewMilestoneForm() {
	milestoneForm.value = { name: '', targetDate: '' }
}

function cancelMilestoneForm() {
	milestoneForm.value = null
}

function onMilestoneDialogOpenChange(isOpen) {
	if (!isOpen) {
		milestoneForm.value = null
	}
}

async function submitMilestoneForm() {
	const name = milestoneForm.value.name.trim()
	if (!name) {
		return
	}
	await api.createMilestone(props.id, { name, targetDate: milestoneForm.value.targetDate || null })
	milestoneForm.value = null
	await load()
}

const milestoneDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelMilestoneForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function toggleMilestoneReached(milestone) {
	await api.updateMilestone(milestone.id, {
		reachedDateProvided: true,
		reachedDate: milestone.reached ? null : new Date().toISOString().slice(0, 10),
	})
	await load()
}

async function deleteMilestone(id) {
	if (!window.confirm(t('projectmanager', 'Delete this milestone?'))) {
		return
	}
	await api.deleteMilestone(id)
	await load()
}

function daysLabel(m) {
	if (m.daysUntil === null) {
		return ''
	}
	if (m.overdue) {
		return t('projectmanager', 'Overdue by {n} days', { n: Math.abs(m.daysUntil) })
	}
	if (m.daysUntil === 0) {
		return t('projectmanager', 'Today')
	}
	return t('projectmanager', '{n} days left', { n: m.daysUntil })
}
</script>

<template>
	<div v-if="overview" class="overview-view">
		<div v-if="overview.milestones.some((m) => m.overdue)" class="warning-banner">
			<AlertCircle :size="18" />
			<span>{{ t('projectmanager', 'A milestone is overdue.') }}</span>
		</div>

		<div class="overview-grid">
			<section class="card">
				<h3>{{ t('projectmanager', 'Phase & milestones') }}</h3>
				<p class="phase-line">
					<StatusPill :status="overview.project.phase" />
					<span class="muted">{{ phaseLabel(overview.project.phase) }}</span>
				</p>
				<ul class="milestone-list">
					<li v-for="m in overview.milestones" :key="m.id" :class="{ overdue: m.overdue, reached: m.reached }">
						<input type="checkbox" :checked="m.reached" @change="toggleMilestoneReached(m)">
						<span class="milestone-name">{{ m.name }}</span>
						<span class="milestone-date">{{ fmtDate(m.targetDate) }}</span>
						<span class="milestone-days">{{ daysLabel(m) }}</span>
						<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" @click="deleteMilestone(m.id)">
							<Delete :size="14" />
						</button>
					</li>
					<li v-if="overview.milestones.length === 0" class="empty-hint">{{ t('projectmanager', 'No milestones yet.') }}</li>
				</ul>
				<button type="button" class="link-btn" @click="openNewMilestoneForm">{{ t('projectmanager', '+ Milestone') }}</button>
				<hr class="sep">
				<dl class="stat-list">
					<div><dt>{{ t('projectmanager', 'Estimated (in scope)') }}</dt><dd>{{ fmtH(overview.summary.estimatedH) }}</dd></div>
					<div><dt>{{ t('projectmanager', 'Done (total)') }}</dt><dd>{{ fmtH(overview.summary.doneTotalH) }}</dd></div>
					<div><dt>{{ t('projectmanager', 'Remaining (in scope)') }}</dt><dd>{{ fmtH(overview.summary.remainingH) }}</dd></div>
				</dl>
			</section>

			<section class="card">
				<h3>{{ t('projectmanager', 'Waiting on client') }}</h3>
				<textarea
					v-model="waitingDraft"
					rows="3"
					:placeholder="t('projectmanager', 'What is currently blocked on the client\'s side')"
					@blur="saveWaiting"></textarea>
				<ul v-if="overview.pendingFeatures.length" class="plain-list">
					<li v-for="f in overview.pendingFeatures" :key="f.id">
						<strong>{{ f.name }}</strong> ({{ f.section }}) — {{ f.externalPending }}
					</li>
				</ul>
			</section>

			<section class="card">
				<h3>{{ t('projectmanager', 'Where I left off') }}</h3>
				<ul class="plain-list">
					<li v-for="(leaf, i) in overview.recentLeaves" :key="i">
						<span class="muted">{{ leaf.workDate }}</span> · {{ leaf.pointCode }} — {{ leaf.description }}
					</li>
					<li v-if="overview.recentLeaves.length === 0" class="empty-hint">{{ t('projectmanager', 'Nothing logged yet.') }}</li>
				</ul>
			</section>

			<section class="card">
				<h3>{{ t('projectmanager', 'In progress') }}</h3>
				<ul class="plain-list">
					<li v-for="p in overview.inProgressPoints" :key="p.id">
						<StatusPill :status="p.status" /> {{ p.moduleCode }} {{ p.code }} — {{ p.description }}
					</li>
					<li v-if="overview.inProgressPoints.length === 0" class="empty-hint">{{ t('projectmanager', 'Nothing in progress.') }}</li>
				</ul>
			</section>

			<section class="card">
				<h3><Star :size="16" class="star-heading" /> {{ t('projectmanager', 'Client-visible points') }}</h3>
				<ul class="plain-list">
					<li v-for="p in overview.clientVisiblePoints" :key="p.id">
						<StatusPill :status="p.status" /> {{ p.code }} — {{ p.description }}
					</li>
					<li v-if="overview.clientVisiblePoints.length === 0" class="empty-hint">{{ t('projectmanager', 'Mark points with the star in the Hours grid to track what the client cares about.') }}</li>
				</ul>
			</section>

			<section class="card">
				<h3>{{ t('projectmanager', 'Quality') }}</h3>
				<dl class="stat-list">
					<div><dt>{{ t('projectmanager', 'Failed tests') }}</dt><dd>{{ overview.quality.failedTests.length }}</dd></div>
					<div><dt>{{ t('projectmanager', 'To test') }}</dt><dd>{{ overview.quality.toTestCount }}</dd></div>
					<div><dt>{{ t('projectmanager', 'Not started features') }}</dt><dd>{{ overview.quality.notStartedFeatureCount }}</dd></div>
				</dl>
				<ul v-if="overview.quality.failedTests.length" class="plain-list">
					<li v-for="f in overview.quality.failedTests" :key="f.id">{{ f.area }} — {{ f.scenario }}</li>
				</ul>
			</section>

			<section class="card card-wide">
				<h3>{{ t('projectmanager', 'Brief') }}</h3>
				<textarea
					v-model="briefDraft"
					rows="4"
					:placeholder="t('projectmanager', 'Objective, what\'s included / not included, acceptance criteria, approver')"
					@blur="saveBrief"></textarea>
			</section>
		</div>

		<NcDialog
			:open="milestoneForm !== null"
			:name="t('projectmanager', '+ Milestone')"
			is-form
			size="small"
			:buttons="milestoneDialogButtons"
			@update:open="onMilestoneDialogOpenChange"
			@submit.prevent="submitMilestoneForm">
			<div v-if="milestoneForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Milestone name') }}</span>
					<input v-model="milestoneForm.name" type="text" autofocus required>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Target date') }}</span>
					<input v-model="milestoneForm.targetDate" type="date">
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.overview-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.warning-banner {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 10px 14px;
	margin-bottom: 16px;
	border-radius: var(--border-radius-large);
	background-color: var(--color-warning, #ffcc00);
	color: var(--color-text-warning, #000);
	font-weight: 600;
}

.overview-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 16px;
}

.card {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 14px 16px;
	background-color: var(--color-main-background);
}

.card-wide {
	grid-column: 1 / -1;
}

.card h3 {
	margin: 0 0 10px;
	font-size: 14px;
	display: flex;
	align-items: center;
	gap: 6px;
}

.star-heading {
	color: #dcb400;
}

.phase-line {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 10px;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.milestone-list {
	list-style: none;
	margin: 0 0 8px;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.milestone-list li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	padding: 2px 0;
}

.milestone-name {
	flex: 1;
}

.milestone-date {
	color: var(--color-text-maxcontrast);
	white-space: nowrap;
}

.milestone-days {
	white-space: nowrap;
	font-size: 12px;
}

.milestone-list li.overdue .milestone-days {
	color: var(--color-error);
	font-weight: 600;
}

.milestone-list li.reached .milestone-name {
	text-decoration: line-through;
	color: var(--color-text-maxcontrast);
}

.sep {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 10px 0;
}

.stat-list {
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.stat-list > div {
	display: flex;
	justify-content: space-between;
	font-size: 13px;
}

.stat-list dt {
	color: var(--color-text-maxcontrast);
}

.stat-list dd {
	margin: 0;
	font-weight: 600;
}

.plain-list {
	list-style: none;
	margin: 8px 0 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 13px;
}

.empty-hint {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}

textarea {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
	resize: vertical;
}

textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 12px;
	padding: 0;
}

.icon-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
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

.dialog-field input {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus {
	border-color: var(--color-primary-element);
	outline: none;
}
</style>
