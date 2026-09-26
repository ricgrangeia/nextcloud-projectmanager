import { t } from '@nextcloud/l10n'

const LABELS = {
	todo: () => t('projectmanager', 'To do'),
	in_progress: () => t('projectmanager', 'In progress'),
	partial: () => t('projectmanager', 'Partial'),
	done: () => t('projectmanager', 'Done'),
	not_started: () => t('projectmanager', 'Not started'),
	to_test: () => t('projectmanager', 'To test'),
	passed: () => t('projectmanager', 'Passed'),
	failed: () => t('projectmanager', 'Failed'),
}

export function statusLabel(status) {
	return LABELS[status] ? LABELS[status]() : status
}

const PHASE_LABELS = {
	briefing: () => t('projectmanager', 'Briefing'),
	development: () => t('projectmanager', 'Development'),
	internal_qa: () => t('projectmanager', 'Internal QA'),
	client_review: () => t('projectmanager', 'Client review'),
	delivered: () => t('projectmanager', 'Delivered'),
	closed: () => t('projectmanager', 'Closed'),
}

export function phaseLabel(phase) {
	return PHASE_LABELS[phase] ? PHASE_LABELS[phase]() : phase
}

const CHANNEL_LABELS = {
	email: () => t('projectmanager', 'Email'),
	session: () => t('projectmanager', 'Session'),
}

export function channelLabel(channel) {
	return CHANNEL_LABELS[channel] ? CHANNEL_LABELS[channel]() : ''
}

const CLIENT_STATUS_LABELS = {
	pending: () => t('projectmanager', 'Pending'),
	presented: () => t('projectmanager', 'Presented'),
	validated: () => t('projectmanager', 'Validated'),
}

export function clientStatusLabel(status) {
	return CLIENT_STATUS_LABELS[status] ? CLIENT_STATUS_LABELS[status]() : status
}
