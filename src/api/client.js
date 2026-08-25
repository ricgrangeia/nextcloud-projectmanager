import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const url = (path) => generateUrl('/apps/projectmanager' + path)
const data = (promise) => promise.then((response) => response.data)

export default {
	listProjects: () => data(axios.get(url('/api/projects'))),
	createProject: (payload) => data(axios.post(url('/api/projects'), payload)),
	createExampleProject: () => data(axios.post(url('/api/projects/example'))),
	getProject: (id) => data(axios.get(url(`/api/projects/${id}`))),
	updateProject: (id, payload) => data(axios.put(url(`/api/projects/${id}`), payload)),
	deleteProject: (id) => data(axios.delete(url(`/api/projects/${id}`))),

	createModule: (projectId, payload) => data(axios.post(url(`/api/projects/${projectId}/modules`), payload)),
	updateModule: (id, payload) => data(axios.put(url(`/api/modules/${id}`), payload)),
	deleteModule: (id) => data(axios.delete(url(`/api/modules/${id}`))),

	createPoint: (moduleId, payload) => data(axios.post(url(`/api/modules/${moduleId}/points`), payload)),
	updatePoint: (id, payload) => data(axios.put(url(`/api/points/${id}`), payload)),
	deletePoint: (id) => data(axios.delete(url(`/api/points/${id}`))),

	createLeaf: (pointId, payload) => data(axios.post(url(`/api/points/${pointId}/leaves`), payload)),
	updateLeaf: (id, payload) => data(axios.put(url(`/api/leaves/${id}`), payload)),
	deleteLeaf: (id) => data(axios.delete(url(`/api/leaves/${id}`))),

	setDayHours: (projectId, date, hours) => data(axios.put(url(`/api/projects/${projectId}/day-hours/${date}`), { hours })),
	deleteDayHours: (projectId, date) => data(axios.delete(url(`/api/projects/${projectId}/day-hours/${date}`))),

	listFeatures: (projectId) => data(axios.get(url(`/api/projects/${projectId}/features`))),
	createFeature: (projectId, payload) => data(axios.post(url(`/api/projects/${projectId}/features`), payload)),
	updateFeature: (id, payload) => data(axios.put(url(`/api/features/${id}`), payload)),
	deleteFeature: (id) => data(axios.delete(url(`/api/features/${id}`))),

	listTests: (projectId) => data(axios.get(url(`/api/projects/${projectId}/tests`))),
	createTest: (projectId, payload) => data(axios.post(url(`/api/projects/${projectId}/tests`), payload)),
	updateTest: (id, payload) => data(axios.put(url(`/api/tests/${id}`), payload)),
	deleteTest: (id) => data(axios.delete(url(`/api/tests/${id}`))),

	exportUrl: (projectId) => url(`/api/projects/${projectId}/export`),
}
