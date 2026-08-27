<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Service;

use OCA\ProjectManager\Db\Client;
use OCA\ProjectManager\Db\ClientMapper;
use OCA\ProjectManager\Db\Project;
use OCA\ProjectManager\Db\ProjectMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

class ClientService {
	public function __construct(
		private ClientMapper $clientMapper,
		private ProjectMapper $projectMapper,
		private TrackerService $trackerService,
	) {
	}

	/** @return Client[] */
	public function findAll(string $userId): array {
		return $this->clientMapper->findAllForUser($userId);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id, string $userId): Client {
		return $this->clientMapper->find($id, $userId);
	}

	public function create(string $userId, string $name): Client {
		$client = new Client();
		$client->setUserId($userId);
		$client->setName($name);
		$client->setCreatedAt(new \DateTimeImmutable());
		return $this->clientMapper->insert($client);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function update(
		int $id,
		string $userId,
		?string $name,
		?float $hourlyRate = null,
		bool $hourlyRateProvided = false,
		?string $currencySymbol = null,
	): Client {
		$client = $this->find($id, $userId);
		if ($name !== null) {
			$client->setName($name);
		}
		if ($hourlyRateProvided) {
			$client->setHourlyRate($hourlyRate);
		}
		if ($currencySymbol !== null) {
			$client->setCurrencySymbol($currencySymbol);
		}
		return $this->clientMapper->update($client);
	}

	/**
	 * Aggregates the summary (estimated/done/remaining hours and, where enabled,
	 * cost) across every project belonging to this client. Since a client's
	 * projects always share its currency, costs can be safely summed.
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function buildSummary(int $id, string $userId): array {
		$client = $this->find($id, $userId);

		$projects = array_values(array_filter(
			$this->projectMapper->findAllForUser($userId, true),
			static fn (Project $p) => $p->getClientId() === $id,
		));

		$totals = [
			'estimatedH' => 0.0, 'estimatedCost' => 0.0,
			'doneInScopeH' => 0.0, 'doneInScopeCost' => 0.0,
			'doneOthersH' => 0.0, 'doneOthersCost' => 0.0,
			'doneTotalH' => 0.0, 'doneTotalCost' => 0.0,
			'remainingH' => 0.0, 'remainingCost' => 0.0,
		];
		$costEnabled = false;
		$projectSummaries = [];

		foreach ($projects as $project) {
			$summary = $this->trackerService->buildGrid($project->getId(), $userId)['summary'];

			$totals['estimatedH'] += $summary['estimatedH'];
			$totals['doneInScopeH'] += $summary['doneInScopeH'];
			$totals['doneOthersH'] += $summary['doneOthersH'];
			$totals['doneTotalH'] += $summary['doneTotalH'];
			$totals['remainingH'] += $summary['remainingH'];

			if ($summary['costEnabled']) {
				$costEnabled = true;
				$totals['estimatedCost'] += $summary['estimatedCost'];
				$totals['doneInScopeCost'] += $summary['doneInScopeCost'];
				$totals['doneOthersCost'] += $summary['doneOthersCost'];
				$totals['doneTotalCost'] += $summary['doneTotalCost'];
				$totals['remainingCost'] += $summary['remainingCost'];
			}

			$projectSummaries[] = [
				'id' => $project->getId(),
				'name' => $project->getName(),
				'archived' => $project->getArchived(),
				'summary' => $summary,
			];
		}

		return [
			'client' => $client,
			'currencySymbol' => $client->getCurrencySymbol(),
			'costEnabled' => $costEnabled,
			'totals' => array_map(static fn (float $v) => round($v, 2), $totals),
			'projects' => $projectSummaries,
		];
	}

	/**
	 * Deletes the client and unassigns it from any projects (projects themselves are kept).
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function delete(int $id, string $userId): void {
		$client = $this->find($id, $userId);
		foreach ($this->projectMapper->findAllForUser($userId, true) as $project) {
			if ($project->getClientId() === $id) {
				$project->setClientId(null);
				$this->projectMapper->update($project);
			}
		}
		$this->clientMapper->delete($client);
	}
}
