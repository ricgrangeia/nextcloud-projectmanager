<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?\DateTimeImmutable getTargetDate()
 * @method void setTargetDate(?\DateTimeImmutable $targetDate)
 * @method ?\DateTimeImmutable getReachedDate()
 * @method void setReachedDate(?\DateTimeImmutable $reachedDate)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method ?string getCommunicationChannel()
 * @method void setCommunicationChannel(?string $communicationChannel)
 * @method string getClientStatus()
 * @method void setClientStatus(string $clientStatus)
 * @method ?int getAcceptancePct()
 * @method void setAcceptancePct(?int $acceptancePct)
 */
class Milestone extends Entity implements \JsonSerializable {
	public const CHANNEL_EMAIL = 'email';
	public const CHANNEL_SESSION = 'session';

	public const CLIENT_STATUS_PENDING = 'pending';
	public const CLIENT_STATUS_PRESENTED = 'presented';
	public const CLIENT_STATUS_VALIDATED = 'validated';

	protected int $projectId = 0;
	protected string $name = '';
	protected ?\DateTimeImmutable $targetDate = null;
	protected ?\DateTimeImmutable $reachedDate = null;
	protected int $sortOrder = 0;
	protected ?string $communicationChannel = null;
	protected string $clientStatus = self::CLIENT_STATUS_PENDING;
	protected ?int $acceptancePct = null;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('targetDate', Types::DATE_IMMUTABLE);
		$this->addType('reachedDate', Types::DATE_IMMUTABLE);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('communicationChannel', Types::STRING);
		$this->addType('clientStatus', Types::STRING);
		$this->addType('acceptancePct', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'name' => $this->name,
			'targetDate' => $this->targetDate?->format('Y-m-d'),
			'reachedDate' => $this->reachedDate?->format('Y-m-d'),
			'sortOrder' => $this->sortOrder,
			'communicationChannel' => $this->communicationChannel,
			'clientStatus' => $this->clientStatus,
			'acceptancePct' => $this->acceptancePct,
		];
	}
}
