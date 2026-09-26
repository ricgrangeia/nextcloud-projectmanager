import { createRouter, createWebHashHistory } from 'vue-router'

import ProjectsListView from '../views/ProjectsListView.vue'
import ProjectView from '../views/ProjectView.vue'
import OverviewView from '../views/OverviewView.vue'
import MilestonesView from '../views/MilestonesView.vue'
import PointsView from '../views/PointsView.vue'
import GridView from '../views/GridView.vue'
import TestsView from '../views/TestsView.vue'
import ClientView from '../views/ClientView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', name: 'projects', component: ProjectsListView },
		{ path: '/clients/:id', name: 'client', component: ClientView, props: true },
		{
			path: '/projects/:id',
			component: ProjectView,
			props: true,
			children: [
				{ path: '', name: 'overview', component: OverviewView, props: true },
				{ path: 'milestones', name: 'milestones', component: MilestonesView, props: true },
				{ path: 'points', name: 'points', component: PointsView, props: true },
				{ path: 'grid', name: 'grid', component: GridView, props: true },
				{ path: 'tests', name: 'tests', component: TestsView, props: true },
			],
		},
	],
})
