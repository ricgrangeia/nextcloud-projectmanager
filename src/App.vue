<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import Cog from 'vue-material-design-icons/Cog.vue'
import { t } from '@nextcloud/l10n'
import api from './api/client.js'
import { state, loadProjects, toggleShowArchived } from './store/index.js'
import HelpButton from './components/HelpButton.vue'
import BackupDialog from './components/BackupDialog.vue'

const route = useRoute()
const router = useRouter()

onMounted(loadProjects)

async function createProject() {
	const name = window.prompt(t('projectmanager', 'New project'))
	if (!name || !name.trim()) {
		return
	}
	const project = await api.createProject({ name: name.trim(), hoursPerWorkingDay: 7 })
	await loadProjects()
	router.push({ name: 'grid', params: { id: project.id } })
}

function openProject(event, project) {
	event?.preventDefault?.()
	router.push({ name: 'grid', params: { id: project.id } })
}

function projectDisplayName(project) {
	return project.archived
		? `${project.name} (${t('projectmanager', 'archived')})`
		: project.name
}

function openProjectSettings(project) {
	router.push({ name: 'grid', params: { id: project.id }, query: { settings: '1' } })
}
</script>

<template>
	<NcContent app-name="projectmanager">
		<NcAppNavigation>
			<template #list>
				<NcAppNavigationItem
					v-for="project in state.projects"
					:key="project.id"
					:name="projectDisplayName(project)"
					:class="{ 'is-archived': project.archived }"
					:active="route.params.id === String(project.id)"
					@click="event => openProject(event, project)">
					<template #actions>
						<NcActionButton :aria-label="t('projectmanager', 'Project settings')" @click.stop="openProjectSettings(project)">
							<template #icon>
								<Cog :size="16" />
							</template>
							{{ t('projectmanager', 'Project settings') }}
						</NcActionButton>
					</template>
				</NcAppNavigationItem>
				<NcAppNavigationItem
					:name="state.showArchived ? t('projectmanager', 'Hide archived projects') : t('projectmanager', 'Show archived projects')"
					@click="toggleShowArchived" />
			</template>
			<template #footer>
				<div class="nav-footer">
					<NcAppNavigationNew
						:text="t('projectmanager', 'New project')"
						@click="createProject" />
					<HelpButton :title="t('projectmanager', 'How Project Manager works')">
						<h4>{{ t('projectmanager', 'Points & Leaves') }}</h4>
						<p>{{ t('projectmanager', 'Work is organized into Modules (groups) and Points — estimable units of work with a status and an optional hour estimate.') }}</p>
						<p>{{ t('projectmanager', 'Log what you did each day as Leaves: short entries tied to a Point and a date.') }}</p>

						<h4>{{ t('projectmanager', 'How hours are calculated') }}</h4>
						<p>{{ t('projectmanager', 'Set how many hours you actually worked each day in the "Hours/day" row.') }}</p>
						<p>{{ t('projectmanager', 'Each day, the app splits 100% of that day across the Points that have Leaves logged that day, proportional to how many Leaves each Point got. Multiplying by the day\'s hours gives each Point\'s "Done" hours; "Remaining" is the estimate minus Done.') }}</p>

						<h4>{{ t('projectmanager', 'Modules vs OTHERS') }}</h4>
						<p>{{ t('projectmanager', 'A module can count towards the estimate, or be marked OTHERS. OTHERS work is still tracked and shown, but kept separate from the original estimate — use it for extra requests, bug fixes, or anything outside what was originally planned.') }}</p>

						<h4>{{ t('projectmanager', 'Summary') }}</h4>
						<p>{{ t('projectmanager', 'The Summary card shows Estimated, Done and Remaining totals in hours and days (1 day = the project\'s hours-per-working-day, set in Project settings — hover a project in the sidebar and click its gear icon).') }}</p>

						<h4>{{ t('projectmanager', 'Features') }}</h4>
						<p>{{ t('projectmanager', 'The Features tab lists what was sold or proposed to the client, tracked against its current status. "Point" is an optional reference to a related Point code — it doesn\'t affect any calculation. "External pending" is for dependencies on someone else.') }}</p>

						<h4>{{ t('projectmanager', 'Tests') }}</h4>
						<p>{{ t('projectmanager', 'The Tests tab is a validation log: one row per scenario you tested, with the expected result and its outcome — use it to document what was checked before delivering.') }}</p>

						<h4>{{ t('projectmanager', 'Cost (optional)') }}</h4>
						<p>{{ t('projectmanager', 'In Project settings you can set an hourly rate and currency symbol; enabling "Show cost in the summary" adds a cost column computed as hours × rate.') }}</p>
					</HelpButton>
					<BackupDialog />
				</div>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<router-view />
		</NcAppContent>
	</NcContent>
</template>

<style scoped>
:deep(.app-navigation-entry.active .app-navigation-entry__name) {
	font-weight: bold;
}

.is-archived :deep(.app-navigation-entry__name) {
	opacity: 0.6;
	font-style: italic;
}

.nav-footer {
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 0 8px;
}

.nav-footer :deep(.app-navigation-new) {
	flex: 1;
}
</style>
