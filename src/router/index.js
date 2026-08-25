import { createRouter, createWebHashHistory } from 'vue-router'

import ProjectsListView from '../views/ProjectsListView.vue'
import ProjectView from '../views/ProjectView.vue'
import GridView from '../views/GridView.vue'
import FeaturesView from '../views/FeaturesView.vue'
import TestsView from '../views/TestsView.vue'

export default createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', name: 'projects', component: ProjectsListView },
		{
			path: '/projects/:id',
			component: ProjectView,
			props: true,
			children: [
				{ path: '', name: 'grid', component: GridView, props: true },
				{ path: 'features', name: 'features', component: FeaturesView, props: true },
				{ path: 'tests', name: 'tests', component: TestsView, props: true },
			],
		},
	],
})
